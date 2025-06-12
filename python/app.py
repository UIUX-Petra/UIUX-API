from flask import Flask, Blueprint, request, jsonify
from sentence_transformers import SentenceTransformer
from torchvision import models, transforms
from torchvision.models import ResNet50_Weights
from PIL import Image
import torch
import werkzeug.utils
import numpy as np
import threading, time
import os
from mysql.connector import pooling, Error
import time
from datetime import timedelta
import heapq
from flask_cors import CORS

app = Flask(__name__)

#SD (Data Structures)
class UserViewStat:
    def __init__(self):
        self.view_stats = {}
        self.last_synced_at = None
        self.lock = threading.Lock()

    def add_view(self, viewer_user_id, owner_user_id, total_views):
        with self.lock:
            if viewer_user_id not in self.view_stats:
                self.view_stats[viewer_user_id] = {}

            if owner_user_id not in self.view_stats[viewer_user_id]:
                self.view_stats[viewer_user_id][owner_user_id] = total_views
            else:
                self.view_stats[viewer_user_id][owner_user_id] += total_views

    def update_last_synced_at(self, timestamp):
        with self.lock:
            self.last_synced_at = timestamp

    def get_last_synced_at(self):
        with self.lock:
            return self.last_synced_at

    def get_top_viewed(self, viewer_user_id, top_n=5):
        with self.lock:
            if viewer_user_id not in self.view_stats:
                return []
            owner_views = self.view_stats[viewer_user_id]
            sorted_views = sorted(owner_views.items(), key=lambda item: item[1], reverse=True)
            return [{"owner_user_id": owner_id, "total_views": views} for owner_id, views in sorted_views[:top_n]]

user_view_stat = UserViewStat()


class User:
    def __init__(self, user_id):
        self.user_id = user_id
        self.following = {}  

    def follow(self, other_user, weight=1):
        self.following[other_user] = weight

class Recommendation:
    def __init__(self):
        self.nodes = {} 

    def add_user(self, user_id):
        if user_id not in self.nodes:
            self.nodes[user_id] = User(user_id)

    def add_follow(self, user_id, follow_id, weight=1):
        self.add_user(user_id)
        self.add_user(follow_id)
        self.nodes[user_id].follow(self.nodes[follow_id], weight)

    def recommend_users(self, user_id, top_n=5):
        if user_id not in self.nodes:
            return []
        user_node = self.nodes[user_id]
        directly_followed = user_node.following
        candidate_scores = {}
        for followed_user, direct_weight in directly_followed.items():
            for second_degree_user, second_weight in followed_user.following.items():
                if second_degree_user != user_node and second_degree_user not in directly_followed:
                    score = direct_weight + second_weight
                    candidate_scores[second_degree_user] = candidate_scores.get(second_degree_user, 0) + score
        sorted_candidates = sorted(candidate_scores.items(), key=lambda x: (-x[1], x[0].user_id))
        return [{"user_id": candidate.user_id, "score": score} for candidate, score in sorted_candidates[:top_n]]

graph = Recommendation()


class UserContribution:
    def __init__(self, user_id, contributions):
        self.user_id = user_id
        self.contributions = contributions

    def __lt__(self, other):
        return self.contributions > other.contributions

class Leaderboard:
    def __init__(self):
        self.tag_leaderboards = {}  
        self.entry_map = {}         

    def update_leaderboard(self, tag_id, user_id, contributions):
        if tag_id not in self.tag_leaderboards:
            self.tag_leaderboards[tag_id] = []
            self.entry_map[tag_id] = {}
        leaderboard = self.tag_leaderboards[tag_id]
        entry_map = self.entry_map[tag_id]
        if user_id in entry_map:
            entry_map[user_id].contributions = contributions
            heapq.heapify(leaderboard)
        else:
            entry = UserContribution(user_id, contributions)
            heapq.heappush(leaderboard, entry)
            entry_map[user_id] = entry

    def get_top_contributors(self, tag_id, top_n):
        if tag_id not in self.tag_leaderboards:
            return []

        leaderboard = self.tag_leaderboards[tag_id]
        top_entries = heapq.nsmallest(top_n, leaderboard)
        return [
            {"user_id": entry.user_id, "contributions": entry.contributions}
            for entry in top_entries
        ]

leaderboard = Leaderboard()


conn_pool = pooling.MySQLConnectionPool(
    pool_name="mypool",
    pool_size=10,
    host='127.0.0.1',
    user='root',
    password='', 
    database='uiux_project'
)


def fetch_from_db(query, params=None):
    conn = None
    try:
        conn = conn_pool.get_connection()
        cursor = conn.cursor(dictionary=True)
        cursor.execute(query, params or ())
        result = cursor.fetchall()
        cursor.close()
        return result
    except Error as e:
        print(f"Database error: {e}")
        raise
    finally:
        if conn and conn.is_connected():
            conn.close()

def build_user_views_from_db():
    query = """
        SELECT v.user_id AS viewer_user_id,
               q.user_id AS owner_user_id,
               COUNT(*) AS new_total_views,
               MAX(v.updated_at) AS last_updated
        FROM views v
        JOIN questions q ON v.viewable_id = q.id
        WHERE v.updated_at > %s
        GROUP BY v.user_id, q.user_id;
    """
    last_synced_at = user_view_stat.get_last_synced_at() or "1970-01-01 00:00:00"
    try:
        rows = fetch_from_db(query, (last_synced_at,))
    except Exception as e:
        print(f"Error while fetching data from the database: {e}")
        return

    for row in rows:
        user_view_stat.add_view(row['viewer_user_id'], row['owner_user_id'], row['new_total_views'])

    if rows:
        max_last_updated = max(row['last_updated'] for row in rows)
        buffered_last_synced_at = (max_last_updated + timedelta(seconds=1)).strftime('%Y-%m-%d %H:%M:%S')
        user_view_stat.update_last_synced_at(buffered_last_synced_at)

def periodic_data_refresh(interval=2):
    while True:
        build_user_views_from_db()
        time.sleep(interval)


last_processed_time_graph = None
graph_lock = threading.Lock()

def build_graph_from_db():
    global last_processed_time_graph
    query = "SELECT * FROM follows"
    params = ()
    if last_processed_time_graph:
        query += " WHERE created_at > %s"
        params = (last_processed_time_graph,)
    query += " ORDER BY created_at ASC"

    try:
        rows = fetch_from_db(query, params)
    except Exception as e:
        print(f"Error fetching follows from DB: {e}")
        return

    for row in rows:
        follower_id = row['follower_id']
        followed_id = row['followed_id']
        graph.add_follow(follower_id, followed_id)
        last_processed_time_graph = row['created_at']

def monitor_recommendation_db(interval=2):
    while True:
        try:
            build_graph_from_db()
            time.sleep(interval)
        except Exception as e:
            print(f"Error monitoring recommendation database: {e}")


last_processed_time_leaderboard = None
db_lock_leaderboard = threading.Lock()

def build_leaderboard_from_db():
    global last_processed_time_leaderboard
    query = """
            SELECT 
                contributions.user_id, 
                contributions.tag_id, 
                SUM(contributions.total_contributions) AS total_contributions,
                MAX(contributions.updated_at) AS last_update
            FROM (
                SELECT 
                    q.user_id, 
                    sq.tag_id, 
                    COUNT(*) AS total_contributions,
                    MAX(q.updated_at) AS updated_at
                FROM questions q
                JOIN subject_questions sq ON q.id = sq.question_id
                GROUP BY q.user_id, sq.tag_id
                UNION ALL
                SELECT 
                    a.user_id, 
                    sq.tag_id, 
                    COUNT(*) AS total_contributions,
                    MAX(a.updated_at) AS updated_at
                FROM answers a
                JOIN questions q ON a.question_id = q.id
                JOIN subject_questions sq ON q.id = sq.question_id
                GROUP BY a.user_id, sq.tag_id
            ) AS contributions
            WHERE (%s IS NULL OR contributions.updated_at > %s)
            GROUP BY contributions.user_id, contributions.tag_id
            ORDER BY last_update ASC, total_contributions DESC;
    """
    params = (last_processed_time_leaderboard, last_processed_time_leaderboard) if last_processed_time_leaderboard else (None, None)
    try:
        rows = fetch_from_db(query, params)
    except Exception as e:
        print(f"Error fetching leaderboard data from DB: {e}")
        return

    with db_lock_leaderboard:
        processed_tags = set()
        for row in rows:
            tag_id = row['tag_id']
            user_id = row['user_id']
            total_contributions = row['total_contributions']
            if (tag_id, user_id) not in processed_tags:
                leaderboard.update_leaderboard(tag_id, user_id, total_contributions)
                processed_tags.add((tag_id, user_id))
        if rows:
            latest_update = max(row['last_update'] for row in rows)
            last_processed_time_leaderboard = latest_update

def monitor_leaderboard_db(interval=2):
    while True:
        try:
            build_leaderboard_from_db()
            time.sleep(interval)
        except Exception as e:
            print(f"Error monitoring leaderboard database: {e}")


@app.route('/top-viewed', methods=['GET'])
def top_viewed_api():
    try:
        user_id = request.args.get('user')
        top_n_str = request.args.get('top_n', '5')

        if not user_id:
            return jsonify({
                "code": 400,
                "success": False,
                "message": "User ID is required.",
                "data": None
            }), 400

        if not top_n_str.isdigit():
            return jsonify({
                "code": 400,
                "success": False,
                "message": "'top_n' parameter must be a positive integer",
                "data": None
            }), 400

        top_n = int(top_n_str)
        if top_n <= 0:
            return jsonify({
                "code": 400,
                "success": False,
                "message": "'top_n' parameter must be greater than 0",
                "data": None
            }), 400

        top_viewed = user_view_stat.get_top_viewed(user_id, top_n)
        return jsonify({
            "code": 200,
            "success": True,
            "message": "Top viewed users retrieved successfully.",
            "data": top_viewed
        }), 200

    except Exception as e:
        print(f"Error di endpoint /top-viewed: {e}")
        return jsonify({
            "code": 500,
            "success": False,
            "message": "An error occurred while processing the request.",
            "error": str(e)
        }), 500

@app.route('/recommend', methods=['GET'])
def recommend_api():
    try:
        user_id = request.args.get('user')
        top_n_str = request.args.get('top_n', '5')

        if not user_id:
            return jsonify({
                "code": 400,
                "success": False,
                "message": "User ID is required.",
                "data": None
            }), 400

        if not top_n_str.isdigit():
            return jsonify({
                "code": 400,
                "success": False,
                "message": "'top_n' parameter must be a positive integer",
                "data": None
            }), 400

        top_n = int(top_n_str)
        if top_n <= 0:
            return jsonify({
                "code": 400,
                "success": False,
                "message": "'top_n' parameter must be greater than 0",
                "data": None
            }), 400

        recommendations = graph.recommend_users(user_id, top_n)
        return jsonify({
            "code": 200,
            "success": True,
            "message": "Recommendations retrieved successfully.",
            "data": recommendations
        }), 200

    except Exception as e:
        print(f"Error di endpoint /recommend: {e}")
        return jsonify({
            "code": 500,
            "success": False,
            "message": "An error occurred while processing the request.",
            "error": str(e)
        }), 500

@app.route('/leaderboard', methods=['GET'])
def leaderboard_api():
    try:
        tag_id = request.args.get('tag')
        top_n_str = request.args.get('top_n', '5')

        if not tag_id:
            return jsonify({
                "code": 400,
                "success": False,
                "message": "Tag ID is required.",
                "data": None
            }), 400

        if not top_n_str.isdigit():
            return jsonify({
                "code": 400,
                "success": False,
                "message": "'top_n' parameter must be a positive integer",
                "data": None
            }), 400

        top_n = int(top_n_str)
        if top_n <= 0:
            return jsonify({
                "code": 400,
                "success": False,
                "message": "'top_n' parameter must be greater than 0",
                "data": None
            }), 400

        top_contributors = leaderboard.get_top_contributors(tag_id, top_n)
        return jsonify({
            "code": 200,
            "success": True,
            "message": "Leaderboard retrieved successfully.",
            "data": top_contributors
        }), 200

    except Exception as e:
        print(f"Error di endpoint /leaderboard: {e}")
        return jsonify({
            "code": 500,
            "success": False,
            "message": "An error occurred while processing the request.",
            "error": str(e)
        }), 500


#AIML (Artificial Intelligence and Machine Learning)
bp = Blueprint('tag_recommender', __name__, url_prefix='/ai')
CORS(app, resources={r"/ai/*": {"origins": "http://localhost:8000"}})
UPLOAD_FOLDER = 'temp_uploads'
if not os.path.exists(UPLOAD_FOLDER):
    os.makedirs(UPLOAD_FOLDER)

text_model = SentenceTransformer('paraphrase-multilingual-MiniLM-L12-v2')
resnet = models.resnet50(weights=ResNet50_Weights.DEFAULT)
image_model = torch.nn.Sequential(*list(resnet.children())[:-1]).eval()
preprocess = transforms.Compose([
    transforms.Resize((224,224)),
    transforms.ToTensor(),
    transforms.Normalize(mean=[0.485,0.456,0.406], std=[0.229,0.224,0.225])
])

tag_prototypes = {}
model_lock = threading.Lock()
epsilon = 0.1
learning_rate = 0.01

def compute_text_embedding(text: str) -> np.ndarray:
    return text_model.encode(text)

def compute_image_embedding(path: str) -> np.ndarray:
    try:
        img = Image.open(path).convert('RGB')
        tensor = preprocess(img).unsqueeze(0)
        with torch.no_grad():
            feat = image_model(tensor).squeeze().numpy()
        return feat
    except Exception as e:
        print(f"Error computing image embedding for path '{path}': {e}")
        return None

def process_and_store_embeddings(question_id: str):
    conn = conn_pool.get_connection()
    cur = conn.cursor(dictionary=True)
    cur.execute("SELECT title, question, image FROM questions WHERE id = %s", (question_id,))
    q_data = cur.fetchone()
    if not q_data:
        cur.close(); conn.close()
        return

    txt_emb = compute_text_embedding((q_data['title'] or '') + ' ' + (q_data['question'] or ''))
    img_emb = compute_image_embedding(q_data['image']) if q_data['image'] else None

    cur.execute(
        """
        INSERT INTO question_embeddings (question_id, text_embedding, image_embedding)
        VALUES (%s, %s, %s) ON DUPLICATE KEY UPDATE
        text_embedding = VALUES(text_embedding), image_embedding = VALUES(image_embedding)
        """, (question_id, txt_emb.tobytes(), img_emb.tobytes() if img_emb is not None else None)
    )
    conn.commit()
    cur.close(); conn.close()
    print(f"Stored embeddings for question {question_id}.")

def update_tag_prototypes():
    conn = conn_pool.get_connection()
    cur = conn.cursor(dictionary=True)
    cur.execute("""
        SELECT sq.tag_id, qe.text_embedding, qe.image_embedding
        FROM subject_questions sq 
        JOIN question_embeddings qe ON sq.question_id = qe.question_id
    """)
    rows = cur.fetchall()
    aggregates = {}
    for row in rows:
        tid = row['tag_id']
        aggregates.setdefault(tid, {'texts': [], 'images': []})
        text_emb = np.frombuffer(row['text_embedding'], dtype=np.float32)
        aggregates[tid]['texts'].append(text_emb)
        if row['image_embedding']:
            image_emb = np.frombuffer(row['image_embedding'], dtype=np.float32)
            aggregates[tid]['images'].append(image_emb)
    cur.execute("SELECT id, name FROM tags")
    all_tags = cur.fetchall()
    cur.close(); conn.close()
    with model_lock:
        global tag_prototypes
        new_prototypes = {}
        for tag in all_tags:
            tid = tag['id']
            seed_txt = tag['name']
            if tid in aggregates and aggregates[tid]['texts']:
                new_prototypes[tid] = {
                    'text':  np.mean(aggregates[tid]['texts'], axis=0),
                    'image': np.mean(aggregates[tid]['images'], axis=0) if aggregates[tid]['images'] else None
                }
            else:
                new_prototypes[tid] = {'text': compute_text_embedding(seed_txt), 'image': None}
        tag_prototypes = new_prototypes
    print("Tag prototypes updated successfully.")
    
def recommend_tags_for(txt_emb: np.ndarray, img_emb: np.ndarray, top_k: int = 5):
    if txt_emb is None: return []
    scores = []
    with model_lock:
        if not tag_prototypes: return []
        for tid, proto in tag_prototypes.items():
            t_sim = np.dot(txt_emb, proto['text']) / (np.linalg.norm(txt_emb) * np.linalg.norm(proto['text']))
            i_sim = 0
            if img_emb is not None and proto['image'] is not None:
                i_sim = np.dot(img_emb, proto['image']) / (np.linalg.norm(img_emb) * np.linalg.norm(proto['image']))
            score = 0.7 * t_sim + 0.3 * i_sim
            if np.random.rand() < epsilon: score = score * np.random.rand()
            scores.append((tid, score))
    scores.sort(key=lambda x: x[1], reverse=True)
    return [tid for tid, _ in scores[:top_k]]

@bp.route('/recommend_tags', methods=['POST'])
def recommend_tags():
    title = request.form.get('title', '')
    question_text = request.form.get('question', '')
    image_file = request.files.get('image')
    top_k = int(request.form.get('top_k', 5))
    
    full_text = title + ' ' + question_text
    txt_emb = compute_text_embedding(full_text)
    
    img_emb = None
    temp_image_path = None
    try:
        if image_file and image_file.filename != '':
            filename = werkzeug.utils.secure_filename(image_file.filename)
            temp_image_path = os.path.join(UPLOAD_FOLDER, filename)
            image_file.save(temp_image_path)
            img_emb = compute_image_embedding(temp_image_path)
        
        recommended_ids = recommend_tags_for(txt_emb, img_emb, top_k)
        if not recommended_ids:
            return jsonify(success=True, recommended_tags=[])
            
        conn = conn_pool.get_connection()
        cur = conn.cursor(dictionary=True)
        format_strings = ','.join(['%s'] * len(recommended_ids))
        cur.execute(f"SELECT id, name FROM tags WHERE id IN ({format_strings})", tuple(recommended_ids))
        tags_from_db = {tag['id']: tag['name'] for tag in cur.fetchall()}
        cur.close(); conn.close()
        response_data = [{"id": tid, "name": tags_from_db.get(tid, "Unknown Tag")} for tid in recommended_ids]
        
        return jsonify(success=True, recommended_tags=response_data)
        
    finally:
        if temp_image_path and os.path.exists(temp_image_path):
            os.remove(temp_image_path)

@bp.route('/process_embeddings', methods=['POST'])
def trigger_embedding_processing():
    data = request.json
    qid = data.get('question_id')
    if not qid:
        return jsonify(success=False, message="question_id is required"), 400
    try:
        threading.Thread(target=process_and_store_embeddings, args=(qid,)).start()
        return jsonify(success=True, message="Embedding processing started."), 202
    except Exception as e:
        return jsonify(success=False, message=f"Failed to start embedding process: {e}"), 500

@bp.route('/tag_feedback', methods=['POST'])
def tag_feedback():
    title = request.form.get('title', '')
    question_text = request.form.get('question', '')
    image_file = request.files.get('image')

    selected_tags = set(request.form.getlist('selected_tags[]'))
    recommended_tags = set(request.form.getlist('recommended_tags[]'))

    if not selected_tags and not recommended_tags:
         return jsonify(success=False, message="selected_tags and recommended_tags are required"), 400

    temp_image_path = None
    try:
        full_text = title + ' ' + question_text
        q_text_emb = compute_text_embedding(full_text)
        q_img_emb = None

        if image_file and image_file.filename != '':
            filename = werkzeug.utils.secure_filename(image_file.filename)
            temp_image_path = os.path.join(UPLOAD_FOLDER, filename)
            image_file.save(temp_image_path)
            q_img_emb = compute_image_embedding(temp_image_path)
        
        with model_lock:
            all_feedback_tags = selected_tags | recommended_tags
            for tid in all_feedback_tags:
                if tid in tag_prototypes:
                    reward = 1.0 if tid in selected_tags else -1.0
                    proto_text = tag_prototypes[tid]['text']
                    tag_prototypes[tid]['text'] = proto_text + learning_rate * reward * (q_text_emb - proto_text)
                    if q_img_emb is not None and tag_prototypes[tid]['image'] is not None:
                        proto_image = tag_prototypes[tid]['image']
                        tag_prototypes[tid]['image'] = proto_image + learning_rate * reward * (q_img_emb - proto_image)
        return jsonify(success=True, message="Feedback processed and model updated in memory.")
    except Exception as e:
        print(f"Error in tag_feedback: {e}")
        return jsonify(success=False, message=f"An error occurred: {e}"), 500
    finally:
        if temp_image_path and os.path.exists(temp_image_path):
            os.remove(temp_image_path)
            
def monitor_prototypes_db(interval=300):
    while True:
        try:
            print("Starting periodic update of tag prototypes...")
            update_tag_prototypes()
            time.sleep(interval)
        except Exception as e:
            print(f"Error during periodic prototype update: {e}")
            time.sleep(60)

if __name__ == '__main__':
    app.register_blueprint(bp)
    
    build_user_views_from_db()
    build_graph_from_db()
    build_leaderboard_from_db()
    update_tag_prototypes() 

    threading.Thread(target=periodic_data_refresh, args=(2,), daemon=True).start()
    threading.Thread(target=monitor_recommendation_db, args=(2,), daemon=True).start()
    threading.Thread(target=monitor_leaderboard_db, args=(2,), daemon=True).start()
    threading.Thread(target=monitor_prototypes_db, args=(300,), daemon=True).start()

    app.run(debug=True, host='0.0.0.0', use_reloader=False)