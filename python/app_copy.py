from flask import Flask, request, jsonify
import threading
from mysql.connector import pooling, Error
import time

app = Flask(__name__)

class UserViewStat:
    def __init__(self):
        """
        Struktur penyimpanan data pakai dictionary dengan hash table:
        {
            viewer_user_id: {
                owner_user_id: total_views
            }
        }
        """
        self.view_stats = {}
        self.lock = threading.Lock()

    def add_view(self, viewer_user_id, owner_user_id, total_views):
        with self.lock:
            if viewer_user_id not in self.view_stats:
                self.view_stats[viewer_user_id] = {}
            if owner_user_id not in self.view_stats[viewer_user_id]:
                self.view_stats[viewer_user_id][owner_user_id] = 0
            self.view_stats[viewer_user_id][owner_user_id] += total_views

    def reset_views(self):
        with self.lock:
            self.view_stats = {}

    def get_top_viewed(self, viewer_user_id, top_n=5):
        with self.lock:
            if viewer_user_id not in self.view_stats:
                return []
            owner_views = self.view_stats[viewer_user_id]
            sorted_views = sorted(owner_views.items(), key=lambda item: item[1], reverse=True)
            return [{"owner_user_id": owner_id, "total_views": views} for owner_id, views in sorted_views[:top_n]]

user_view_stat = UserViewStat()

conn_pool = pooling.MySQLConnectionPool(
    pool_name="mypool",
    pool_size=10,
    host='127.0.0.1',
    user='root',
    password='', 
    database='tekweb_project'
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
               v.viewable_id AS question_id,
               q.user_id AS owner_user_id,
               v.total AS total_views
        FROM views v
        JOIN questions q ON v.viewable_id = q.id
    """
    try:
        rows = fetch_from_db(query)
    except Exception as e:
        print(f"Gagal mengambil data dari database: {e}")
        return

    user_view_stat.reset_views()

    print(f"Memuat {len(rows)} baris data dari database...")
    for row in rows:
        viewer_user_id = row['viewer_user_id']
        owner_user_id = row['owner_user_id']
        total_views = row['total_views']
        user_view_stat.add_view(viewer_user_id, owner_user_id, total_views)
    print("Data view_stats berhasil dimuat ulang.")

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

def periodic_data_refresh(interval=300):
    while True:
        print("Memperbarui data view_stats dari database...")
        build_user_views_from_db()
        print("Pembaharuan selesai.")
        time.sleep(interval)

if __name__ == '__main__':
    build_user_views_from_db()
    refresh_thread = threading.Thread(target=periodic_data_refresh, args=(300,), daemon=True)
    refresh_thread.start()
    app.run(debug=True, host='0.0.0.0')
