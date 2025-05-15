from flask import Flask, request, jsonify
import threading
from mysql.connector import pooling, Error
import time
from datetime import datetime, timedelta
import heapq

app = Flask(__name__)

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

if __name__ == '__main__':
    build_user_views_from_db()
    build_graph_from_db()
    build_leaderboard_from_db()

    threading.Thread(target=periodic_data_refresh, args=(2,), daemon=True).start()
    threading.Thread(target=monitor_recommendation_db, args=(2,), daemon=True).start()
    threading.Thread(target=monitor_leaderboard_db, args=(2,), daemon=True).start()

    app.run(debug=True, host='0.0.0.0')
