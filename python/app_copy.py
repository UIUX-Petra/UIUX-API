from flask import Flask, request, jsonify
import threading
from mysql.connector import pooling, Error
import time
from datetime import datetime, timedelta

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
               q.user_id AS owner_user_id,
               COUNT(*) AS new_total_views,
               MAX(v.updated_at) AS last_updated
        FROM views v
        JOIN questions q ON v.viewable_id = q.id
        WHERE v.updated_at > %s
        GROUP BY v.user_id, q.user_id;
    """
    last_synced_at = user_view_stat.get_last_synced_at() or "1970-01-01 00:00:00"
    print(f"[{datetime.now()}] Fetching data with last_synced_at: {last_synced_at}")
    try:
        rows = fetch_from_db(query, (last_synced_at,))
    except Exception as e:
        print(f"Error while fetching data from the database: {e}")
        return

    print(f"[{datetime.now()}] Fetched {len(rows)} rows from the database.")
    for row in rows:
        print(f"[{datetime.now()}] Updating viewer {row['viewer_user_id']} for owner {row['owner_user_id']} with {row['new_total_views']} new views.")
        user_view_stat.add_view(row['viewer_user_id'], row['owner_user_id'], row['new_total_views'])

    if rows:
        max_last_updated = max(row['last_updated'] for row in rows)
        buffered_last_synced_at = (max_last_updated + timedelta(seconds=1)).strftime('%Y-%m-%d %H:%M:%S')
        user_view_stat.update_last_synced_at(buffered_last_synced_at)
        print(f"[{datetime.now()}] Updated last_synced_at to: {buffered_last_synced_at}")
    else:
        print(f"[{datetime.now()}] No new data to sync.")

    print(f"[{datetime.now()}] View stats synced with the database.")

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

def periodic_data_refresh(interval=2):
    while True:
        print(f"[{datetime.now()}] Memperbarui data view_stats dari database...")
        build_user_views_from_db()
        print(f"[{datetime.now()}] Pembaharuan selesai.")
        time.sleep(interval)

if __name__ == '__main__':
    build_user_views_from_db()
    refresh_thread = threading.Thread(target=periodic_data_refresh, args=(2,), daemon=True)
    refresh_thread.start()
    app.run(debug=True, host='0.0.0.0')
