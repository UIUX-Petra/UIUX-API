from flask import Flask, Blueprint, request, jsonify
from sentence_transformers import SentenceTransformer
from torchvision import models, transforms
from torchvision.models import ResNet50_Weights
from PIL import Image
import torch
import base64
import numpy as np
import pandas as pd
import threading, time
import os
import re
from mysql.connector import pooling, Error
import time
from datetime import timedelta
import heapq
from dotenv import load_dotenv
from flask_cors import CORS
from functools import lru_cache
from deepface import DeepFace
import cv2
import joblib
from Sastrawi.Stemmer.StemmerFactory import StemmerFactory
from Sastrawi.StopWordRemover.StopWordRemoverFactory import StopWordRemoverFactory
import spacy
from langdetect import detect
from langdetect.lang_detect_exception import LangDetectException
from fuzzywuzzy import fuzz
import io

app = Flask(__name__)
load_dotenv()

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
    host=os.getenv('DB_HOST', '127.0.0.1'),
    user=os.getenv('DB_USERNAME', 'root'),
    password=os.getenv('DB_PASSWORD', ''),
    database=os.getenv('DB_DATABASE', 'uiux_project'),
    port=os.getenv('DB_PORT', 3306)
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

def periodic_data_refresh(interval=30):
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
# --- Universal Text/Image Models ---
text_model = SentenceTransformer('paraphrase-multilingual-MiniLM-L12-v2')
resnet = models.resnet50(weights=ResNet50_Weights.DEFAULT)
image_model = torch.nn.Sequential(*list(resnet.children())[:-1]).eval()
preprocess = transforms.Compose([
    transforms.Resize((224,224)),
    transforms.ToTensor(),
    transforms.Normalize(mean=[0.485,0.456,0.406], std=[0.229,0.224,0.225])
])

try:
    stemmer_id = StemmerFactory().create_stemmer()
    stopword_remover_id = StopWordRemoverFactory().create_stop_word_remover()
    nlp_en = spacy.load("en_core_web_sm")
    print("Successfully loaded Sastrawi and spaCy models.")
except Exception as e:
    print(f"ERROR loading NLP helper models: {e}")
    stemmer_id, stopword_remover_id, nlp_en = None, None, None

def simple_preprocess(text: str) -> str:
    if not isinstance(text, str): return ""
    text = text.lower()
    text = re.sub(r'<.*?>', '', text)
    text = re.sub(r'\s+', ' ', text).strip()
    return text

def detect_language(text: str) -> str:
    try:
        if text and len(text.strip()) > 10: return detect(text)
        return "unknown"
    except LangDetectException:
        return "unknown"

def multilingual_preprocess(text: str) -> str:
    cleaned_text = simple_preprocess(text)
    if not cleaned_text: return ""
    lang = detect_language(cleaned_text)
    if lang == 'id' and stemmer_id and stopword_remover_id:
        stemmed_text = stemmer_id.stem(cleaned_text)
        return stopword_remover_id.remove(stemmed_text)
    elif lang == 'en' and nlp_en:
        doc = nlp_en(cleaned_text)
        tokens = [token.lemma_ for token in doc if not token.is_stop and not token.is_punct]
        return " ".join(tokens)
    else:
        return cleaned_text
    
#tag-recommender
tag_bp = Blueprint('tag_recommender', __name__, url_prefix='/ai')
CORS(app, resources={r"/ai/*": {"origins": "http://localhost:8000"}})

tag_prototypes = {}
model_lock = threading.Lock()
epsilon = 0.1
learning_rate = 0.01
TEXT_WEIGHT = 0.7
IMAGE_WEIGHT = 0.3
SEED_WEIGHT_NEW_TAG = 1.0
SEED_WEIGHT_JUVENILE_TAG = 0.6
SEED_WEIGHT_MATURE_TAG = 0.2
JUVENILE_THRESHOLD = 20
MATURITY_THRESHOLD = 150
NEW_CONFIDENCE_THRESHOLD = 0.25
JUVENILE_CONFIDENCE_THRESHOLD = 0.5
MATURE_CONFIDENCE_THRESHOLD = 0.8

@lru_cache(maxsize=1024)
def compute_text_embedding(text: str) -> np.ndarray:
    return text_model.encode(text)

def compute_image_embedding(image_source) -> np.ndarray:
    try:
        img = Image.open(image_source).convert('RGB')
        tensor = preprocess(img).unsqueeze(0)
        with torch.no_grad():
            feat = image_model(tensor).squeeze().numpy()
        return feat
    except Exception as e:
        print(f"Error computing image embedding: {e}")
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
    img_emb = None
    if q_data.get('image'):
        laravel_public_path = os.getenv('PUBLIC_PATH', '../public')
        if laravel_public_path:
            full_image_path = os.path.join(laravel_public_path, 'storage', q_data['image'])
            print(f"Attempting to load image from: {full_image_path}")
            img_emb = compute_image_embedding(full_image_path)
        else:
            print("Warning: PUBLIC_PATH environment variable not set. Cannot process image.")

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

def update_tag_model():
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
        aggregates.setdefault(tid, {'texts': [], 'images': [], 'count': 0})
        text_emb = np.frombuffer(row['text_embedding'], dtype=np.float32)
        aggregates[tid]['texts'].append(text_emb)
        if row['image_embedding']:
            image_emb = np.frombuffer(row['image_embedding'], dtype=np.float32)
            aggregates[tid]['images'].append(image_emb)
        aggregates[tid]['count'] += 1
    cur.execute("SELECT id, name, abbreviation, description FROM tags")
    all_tags = cur.fetchall()
    cur.close(); conn.close()
    with model_lock:
        global tag_prototypes
        new_prototypes = {}
        for tag in all_tags:
            tid = tag['id']
            text_parts = [tag['name']]
            if tag.get('abbreviation'): text_parts.append(tag['abbreviation'])
            if tag.get('description'): text_parts.append(tag['description'])
            seed_text_embedding = compute_text_embedding(' '.join(text_parts))
            
            tag_data = aggregates.get(tid)
            if tag_data and tag_data['texts']:
                question_count = tag_data['count']
                if question_count < JUVENILE_THRESHOLD:
                    seed_weight = SEED_WEIGHT_NEW_TAG
                elif question_count < MATURITY_THRESHOLD:
                    seed_weight = SEED_WEIGHT_JUVENILE_TAG
                else:
                    seed_weight = SEED_WEIGHT_MATURE_TAG
                history_weight = 1.0 - seed_weight
                questions_mean_text = np.mean(tag_data['texts'], axis=0)
                hybrid_text_prototype = (seed_weight * seed_text_embedding) + (history_weight * questions_mean_text)
                hybrid_image_prototype = np.mean(tag_data['images'], axis=0) if tag_data['images'] else None
                new_prototypes[tid] = {
                    'text':  hybrid_text_prototype,
                    'image': hybrid_image_prototype,
                    'count': question_count
                }
            else:
                new_prototypes[tid] = {
                    'text': seed_text_embedding, 
                    'image': None,
                    'count': 0 
                }
        tag_prototypes = new_prototypes
    print("Tag prototypes updated successfully.")
    
def get_all_tags_score(txt_emb: np.ndarray, img_emb: np.ndarray):
    if txt_emb is None: return []
    scores = []
    with model_lock:
        if not tag_prototypes: return []
        for tid, proto in tag_prototypes.items():
            t_sim = np.dot(txt_emb, proto['text']) / (np.linalg.norm(txt_emb) * np.linalg.norm(proto['text']))
            i_sim = 0
            if img_emb is not None and proto['image'] is not None:
                norm_img = np.linalg.norm(img_emb)
                norm_proto = np.linalg.norm(proto['image'])
                if norm_img > 0 and norm_proto > 0:
                    i_sim = np.dot(img_emb, proto['image']) / (norm_img * norm_proto)
            score = TEXT_WEIGHT * t_sim + IMAGE_WEIGHT * i_sim
            if np.random.rand() < epsilon: score = score * np.random.rand()
            scores.append((tid, score, proto['count']))
    scores.sort(key=lambda x: x[1], reverse=True)
    return scores

@tag_bp.route('/recommend_tags', methods=['POST'])
def recommend_tags():
    title = request.form.get('title', '')
    question_text = request.form.get('question', '')
    image_file = request.files.get('image')
    
    full_text = title + ' ' + question_text
    txt_emb = compute_text_embedding(full_text)
    
    img_emb = None
    if image_file and image_file.filename != '':
        img_emb = compute_image_embedding(image_file.stream)
    
    all_scored_tags  = get_all_tags_score(txt_emb, img_emb)
    if not all_scored_tags :
        return jsonify(success=True, recommended_tags=[])
    
    recommended_ids = []
    for tid, score, count in all_scored_tags:
        if count < JUVENILE_THRESHOLD:
            dynamic_threshold = NEW_CONFIDENCE_THRESHOLD
        elif count < MATURITY_THRESHOLD:
            dynamic_threshold = JUVENILE_CONFIDENCE_THRESHOLD
        else:
            dynamic_threshold = MATURE_CONFIDENCE_THRESHOLD
        if score >= dynamic_threshold:
            recommended_ids.append(tid)
    
    for tid,score,count in all_scored_tags:
        print(score)
        
    if not recommended_ids and all_scored_tags:
        best_tag_id = all_scored_tags[0][0] 
        recommended_ids = [best_tag_id]
    
    conn = conn_pool.get_connection()
    cur = conn.cursor(dictionary=True)
    format_strings = ','.join(['%s'] * len(recommended_ids))
    cur.execute(f"SELECT id, name FROM tags WHERE id IN ({format_strings})", tuple(recommended_ids))
    tags_from_db = {tag['id']: tag['name'] for tag in cur.fetchall()}
    cur.close(); conn.close()
    response_data = [{"id": tid, "name": tags_from_db.get(tid, "Unknown Tag")} for tid in recommended_ids]
    return jsonify(success=True, recommended_tags=response_data)

@tag_bp.route('/process_embeddings', methods=['POST'])
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

@tag_bp.route('/tag_feedback', methods=['POST'])
def tag_feedback():
    title = request.form.get('title', '')
    question_text = request.form.get('question', '')
    image_file = request.files.get('image')
    selected_tags = set(request.form.getlist('selected_tags[]'))
    recommended_tags = set(request.form.getlist('recommended_tags[]'))

    if not selected_tags and not recommended_tags:
         return jsonify(success=False, message="selected_tags and recommended_tags are required"), 400

    try:
        full_text = title + ' ' + question_text
        text_emb = compute_text_embedding(full_text)
        
        img_emb = None
        if image_file and image_file.filename != '':
            img_emb = compute_image_embedding(image_file.stream)
            
        tags_to_punish = recommended_tags - selected_tags
        with model_lock:
            for tid in selected_tags:
                if tid in tag_prototypes:
                    proto_text = tag_prototypes[tid]['text']
                    tag_prototypes[tid]['text'] = proto_text + learning_rate * 1.0 * (text_emb - proto_text)
                    if img_emb is not None and tag_prototypes[tid]['image'] is not None:
                        proto_image = tag_prototypes[tid]['image']
                        tag_prototypes[tid]['image'] = proto_image + learning_rate * 1.0 * (img_emb - proto_image)

            for tid in tags_to_punish:
                if tid in tag_prototypes:
                    proto_text = tag_prototypes[tid]['text']
                    tag_prototypes[tid]['text'] = proto_text + learning_rate * -1.0 * (text_emb - proto_text)
                    if img_emb is not None and tag_prototypes[tid]['image'] is not None:
                        proto_image = tag_prototypes[tid]['image']
                        tag_prototypes[tid]['image'] = proto_image + learning_rate * -1.0 * (img_emb - proto_image)
        return jsonify(success=True, message="Feedback processed and model updated in memory.")
    except Exception as e:
        print(f"Error in tag_feedback: {e}")
        return jsonify(success=False, message=f"An error occurred: {e}"), 500
            
def monitor_tag_model_db(interval=300):
    while True:
        try:
            print("Starting periodic update of tag model...")
            update_tag_model()
            time.sleep(interval)
        except Exception as e:
            print(f"Error during periodic prototype update: {e}")
            time.sleep(60)
            
#face-recognition  
face_bp = Blueprint('face_auth', __name__, url_prefix='/ai')
MODEL_NAME = 'VGG-Face'
DISTANCE_METRIC = 'cosine'
DISTANCE_THRESHOLD = 0.4

def b64_to_image(b64_string):
    if "," in b64_string:
        b64_string = b64_string.split(',')[1]
    img_bytes = base64.b64decode(b64_string)
    img_array = np.frombuffer(img_bytes, dtype=np.uint8)
    image = cv2.imdecode(img_array, cv2.IMREAD_COLOR)
    return image

@face_bp.route('/register_face', methods=['POST'])
def register_face():
    if not conn_pool:
        return jsonify(success=False, message="Database connection not available."), 500

    data = request.json
    user_id = data.get('user_id')
    image_b64_list = data.get('images')

    if not user_id or not image_b64_list:
        return jsonify(success=False, message="user_id and a list of images are required."), 400

    embeddings = []
    for image_b64 in image_b64_list:
        try:
            image = b64_to_image(image_b64)
            embedding_obj = DeepFace.represent(
                img_path=image,
                model_name=MODEL_NAME,
                enforce_detection=True
            )
            embeddings.append(embedding_obj[0]["embedding"])
        except ValueError as e:
            print(f"No face detected in one of the registration images for user {user_id}. Skipping. Error: {e}")
            continue

    if not embeddings:
        return jsonify(success=False, message="Could not detect a face in any of the provided images."), 400

    avg_embedding = np.mean(embeddings, axis=0).astype(np.float32)

    try:
        conn = conn_pool.get_connection()
        cur = conn.cursor()
        
        cur.execute(
            "UPDATE users SET face_embedding = %s WHERE id = %s",
            (avg_embedding.tobytes(), user_id)
        )
        conn.commit()

        if cur.rowcount == 0:
            return jsonify(success=False, message=f"User with ID {user_id} not found."), 404
        
        print(f"Successfully registered and stored face embedding for user: {user_id}")
        return jsonify(success=True, message=f"Face registered successfully for user {user_id}.")

    except Exception as e:
        print(f"Database error during face registration: {e}")
        return jsonify(success=False, message="An internal error occurred during registration."), 500
    finally:
        if 'conn' in locals() and conn.is_connected():
            cur.close()
            conn.close()

@face_bp.route('/login_face', methods=['POST'])
def login_face():
    """
    Authenticates a user by comparing their face to all registered faces in the database.
    Expects: { "image": "b64_img" }
    """
    if not conn_pool:
        return jsonify(success=False, message="Database connection not available."), 500

    data = request.json
    image_b64 = data.get('image')

    if not image_b64:
        return jsonify(success=False, message="Image is required."), 400

    try:
        login_image = b64_to_image(image_b64)
        login_embedding_obj = DeepFace.represent(
            img_path=login_image,
            model_name=MODEL_NAME,
            enforce_detection=True
        )
        login_embedding = np.array(login_embedding_obj[0]["embedding"], dtype=np.float32)
    except ValueError:
        return jsonify(success=False, message="No face detected in the login image."), 400

    try:
        conn = conn_pool.get_connection()
        cur = conn.cursor(dictionary=True)
        
        cur.execute("SELECT id, username, face_embedding FROM users WHERE face_embedding IS NOT NULL")
        registered_users = cur.fetchall()

        if not registered_users:
            return jsonify(success=False, message="No users are registered for face login."), 404

    except Exception as e:
        print(f"Database error during face login: {e}")
        return jsonify(success=False, message="An internal error occurred."), 500
    finally:
        if 'conn' in locals() and conn.is_connected():
            cur.close()
            conn.close()
            
    best_match_user = None
    min_distance = float('inf')

    for user in registered_users:
        stored_embedding = np.frombuffer(user['face_embedding'], dtype=np.float32)
        distance = DeepFace.dst.findCosineDistance(login_embedding, stored_embedding)

        if distance < min_distance:
            min_distance = distance
            best_match_user = user

    if best_match_user and min_distance <= DISTANCE_THRESHOLD:
        print(f"Face match found for user {best_match_user['username']} with distance {min_distance}")
        return jsonify(
            success=True,
            message=f"Login successful. Welcome, {best_match_user['username']}!",
            user_id=best_match_user['id'],
            username=best_match_user['username'],
            distance=min_distance
        )
    else:
        print(f"No suitable match found. Closest distance was {min_distance}")
        return jsonify(success=False, message="Face not recognized or does not match any user.")

#question-similarity
try:
    duplicate_classifier_model = joblib.load('duplicate_classifier_model.pkl')
    stemmer_id = StemmerFactory().create_stemmer()
    stopword_remover_id = StopWordRemoverFactory().create_stop_word_remover()
    nlp_en = spacy.load("en_core_web_sm")
    print("Successfully loaded duplicate detection models.")
except Exception as e:
    print(f"ERROR loading duplicate detection models: {e}")
    duplicate_classifier_model = None

def jaccard_similarity(list1, list2):
    s1 = set(list1)
    s2 = set(list2)
    if not s1 and not s2: return 0.0
    return len(s1.intersection(s2)) / len(s1.union(s2))

def get_image_embedding_from_path(image_path_suffix: str) -> np.ndarray:
    if not image_path_suffix:
        return None
    
    laravel_public_path = os.getenv('PUBLIC_PATH', '../public')
    if laravel_public_path:
        full_image_path = os.path.join(laravel_public_path, 'storage', image_path_suffix)
        return compute_image_embedding(full_image_path)
    return None

def create_features_from_embeddings(
    q1_title, q1_question, q1_text_emb, q1_img_emb,
    q2_title, q2_question, q2_text_emb, q2_img_emb
):
    t1_clean = multilingual_preprocess(q1_title)
    q1_clean = multilingual_preprocess(q1_question)
    t2_clean = multilingual_preprocess(q2_title)
    q2_clean = multilingual_preprocess(q2_question)
    
    features = {}
    
    features['len_char_q1'] = len(q1_question)
    features['len_char_q2'] = len(q2_question)
    features['len_word_q1'] = len(q1_question.split())
    features['len_word_q2'] = len(q2_question.split())
    features['len_diff_word'] = abs(features['len_word_q1'] - features['len_word_q2']) / max(features['len_word_q1'], features['len_word_q2'], 1)
    
    try:
        fuzz_scores = [fuzz.QRatio(t1_clean, t2_clean)/100.0, fuzz.QRatio(q1_clean, q2_clean)/100.0]
        features['fuzz_avg'] = np.mean(fuzz_scores)
        features['fuzz_max'] = np.max(fuzz_scores)

        cosine_sim = 0.0
        if q1_text_emb is not None and q2_text_emb is not None:
            norm1 = np.linalg.norm(q1_text_emb)
            norm2 = np.linalg.norm(q2_text_emb)
            if norm1 > 0 and norm2 > 0:
                cosine_sim = np.dot(q1_text_emb, q2_text_emb) / (norm1 * norm2)
        
        features['cosine_max'] = cosine_sim
        features['cosine_avg'] = cosine_sim
        features['cosine_cross_max'] = cosine_sim
        
        t1_tokens, q1_tokens = t1_clean.split(), q1_clean.split()
        t2_tokens, q2_tokens = t2_clean.split(), q2_clean.split()
        features['jaccard_title'] = jaccard_similarity(t1_tokens, t2_tokens)
        features['jaccard_question'] = jaccard_similarity(q1_tokens, q2_tokens)
        features['jaccard_cross_avg'] = jaccard_similarity(set(t1_tokens + q1_tokens), set(t2_tokens + q2_tokens))

        features['image_similarity'] = 0.0
        if q1_img_emb is not None and q2_img_emb is not None:
            norm1 = np.linalg.norm(q1_img_emb)
            norm2 = np.linalg.norm(q2_img_emb)
            if norm1 > 0 and norm2 > 0:
                features['image_similarity'] = np.dot(q1_img_emb, q2_img_emb) / (norm1 * norm2)
                
    except Exception as e:
        print(f"ERROR creating features from embeddings: {e}")
        keys_to_zero = [
            'fuzz_avg', 'fuzz_max', 'cosine_avg', 'cosine_max', 'cosine_cross_max', 
            'jaccard_title', 'jaccard_question', 'jaccard_cross_avg', 'image_similarity'
        ]
        for key in keys_to_zero:
            features[key] = 0.0     
    return features

duplicate_bp = Blueprint('duplicate_detector', __name__, url_prefix='/ai')
@duplicate_bp.route('/find_similar_by_tags', methods=['POST'])
def find_similar_by_tags():
    if duplicate_classifier_model is None:
        return jsonify(success=False, message="Duplicate detection model is not ready."), 503

    title = request.form.get('title', '')
    question_text = request.form.get('question', '')
    tag_ids_str = request.form.get('tag_ids', '')
    image_file = request.files.get('image')

    if not title or not tag_ids_str:
        return jsonify(success=False, message="Title and tag_ids are required."), 400

    q1_text_emb = compute_text_embedding(f"{title} {question_text}")
    q1_img_emb = None
    if image_file and image_file.filename != '':
        # Baca stream ke dalam memori dulu
        image_data = io.BytesIO(image_file.read())
        # Kirim data dari memori
        q1_img_emb = compute_image_embedding(image_data)

    tag_ids = [tid.strip() for tid in tag_ids_str.split(',') if tid.strip()]
    if not tag_ids:
        return jsonify(success=True, duplicates=[])

    try:
        conn = conn_pool.get_connection()
        cur = conn.cursor(dictionary=True)

        format_strings = ','.join(['%s'] * len(tag_ids))
        query = f"""
            SELECT q.id, q.title, q.question, qe.text_embedding, qe.image_embedding
            FROM questions q
            JOIN subject_questions sq ON q.id = sq.question_id
            JOIN question_embeddings qe ON q.id = qe.question_id
            WHERE sq.tag_id IN ({format_strings})
            LIMIT 200; 
        """
        cur.execute(query, tuple(tag_ids))
        candidates = cur.fetchall()
        cur.close()
        conn.close()

    except Exception as e:
        return jsonify(success=False, message="Could not retrieve candidates."), 500

    if not candidates:
        return jsonify(success=True, message="No similar questions found for these tags.", duplicates=[])

    potential_duplicates = []
    for candidate in candidates:
        q2_text_emb = np.frombuffer(candidate['text_embedding'], dtype=np.float32) if candidate['text_embedding'] else None
        q2_img_emb = np.frombuffer(candidate['image_embedding'], dtype=np.float32) if candidate['image_embedding'] else None

        if q2_text_emb is None: 
            print(f"WARNING: Candidate {candidate.get('id')} has no text embedding. Skipping feature creation.")
            continue

        features_dict = create_features_from_embeddings(
            title, question_text, q1_text_emb, q1_img_emb,
            candidate.get('title', ''), candidate.get('question', ''), q2_text_emb, q2_img_emb
        )

        expected_features_order = duplicate_classifier_model.feature_names_in_

        feature_values = [features_dict.get(col, 0.0) for col in expected_features_order]
        features_df = pd.DataFrame([feature_values], columns=expected_features_order)

        probability = duplicate_classifier_model.predict_proba(features_df)[:, 1][0] 
        print(f"DEBUG: Candidate ID {candidate.get('id')} (Title: '{candidate.get('title', '')}'): Probability = {round(float(probability), 4)}") # <--- TAMBAH INI

        if probability > 0.5:
            potential_duplicates.append({
                "id": candidate.get('id'),
                "title": candidate.get('title', ''),
                "question": candidate.get('question', ''),
                "duplication_probability": round(float(probability), 4)
            })

    potential_duplicates.sort(key=lambda x: x['duplication_probability'], reverse=True)
    return jsonify(success=True, duplicates=potential_duplicates)

if __name__ == '__main__':
    app.register_blueprint(tag_bp)
    app.register_blueprint(face_bp)
    app.register_blueprint(duplicate_bp)
    
    build_user_views_from_db()
    build_graph_from_db()
    build_leaderboard_from_db()
    update_tag_model()

    threading.Thread(target=periodic_data_refresh, args=(2,), daemon=True).start()
    threading.Thread(target=monitor_recommendation_db, args=(2,), daemon=True).start()
    threading.Thread(target=monitor_leaderboard_db, args=(2,), daemon=True).start()
    threading.Thread(target=monitor_tag_model_db, args=(300,), daemon=True).start()

    app.run(debug=True, host='0.0.0.0', use_reloader=False)