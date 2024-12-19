from mysql.connector import pooling
from flask import Flask, request, jsonify
import threading
import time

app = Flask(__name__)

class ViewTracker:
    def __init__(self):
        # Map: viewer_user_id -> {owner_user_id: view_count}
        self.user_view_count = {}

    def record_view(self, viewer_user_id, owner_user_id):
        if viewer_user_id not in self.user_view_count:
            self.user_view_count[viewer_user_id] = {}
        if owner_user_id not in self.user_view_count[viewer_user_id]:
            self.user_view_count[viewer_user_id][owner_user_id] = 0
        self.user_view_count[viewer_user_id][owner_user_id] += 1

    def get_most_viewed_owner(self, viewer_user_id):
        print(self.user_view_count)
        if viewer_user_id not in self.user_view_count:
            return None
        owner_views = self.user_view_count[viewer_user_id]
        most_viewed_owner = max(owner_views, key=owner_views.get)
        return most_viewed_owner, owner_views[most_viewed_owner]

view_tracker = ViewTracker()

conn_pool = pooling.MySQLConnectionPool(
    pool_name="mypool",
    pool_size=10,
    host='127.0.0.1',
    user='root',
    password='',
    database='tekweb_project'
)

last_processed_time_views = None
db_lock = threading.Lock()

def fetch_from_db(query, params=None):
    conn = None
    cursor = None
    try:
        conn = conn_pool.get_connection()
        cursor = conn.cursor(dictionary=True)
        cursor.execute(query, params or ())
        results = cursor.fetchall()
        return results
    except Exception as e:
        print(f"Database error: {e}")
        raise
    finally:
        if cursor:
            cursor.close()
        if conn:
            conn.close()


def build_views_from_db():
    global last_processed_time_views
    query = """
        SELECT 
            views.user_id AS viewer_user_id, 
            questions.user_id AS owner_user_id,
            views.created_at
        FROM views
        JOIN questions 
        ON views.viewable_id = questions.id 
    """
    params = ()
    if last_processed_time_views:
        query += " AND views.created_at > %s"
        params = (last_processed_time_views,)
    query += " ORDER BY views.created_at ASC"

    rows = fetch_from_db(query, params)
    for row in rows:
        view_tracker.record_view(row['viewer_user_id'], row['owner_user_id'])
        last_processed_time_views = row['created_at']

def monitor_views_db():
    while True:
        try:
            build_views_from_db()
            time.sleep(2)
        except Exception as e:
            print(f"Error monitoring views database: {e}")

@app.route('/most_viewed', methods=['GET'])
def most_viewed_api():
    try:
        viewer_user_id = request.args.get('viewer_user_id')
        if not viewer_user_id:
            return jsonify({
                "code": 400,
                "success": False,
                "message": "Viewer User ID is required.",
                "data": None
            }), 400

        result = view_tracker.get_most_viewed_owner(viewer_user_id)
        print(result)
        if not result:
            return jsonify({
                "code": 404,
                "success": False,
                "message": "No data found for this viewer.",
                "data": None
            }), 404

        owner_user_id, view_count = result
        return jsonify({
            "code": 200,
            "success": True,
            "message": "Most viewed owner retrieved successfully.",
            "data": {
                "owner_user_id": owner_user_id,
                "view_count": view_count
            }
        })

    except Exception as e:
        return jsonify({
            "code": 500,
            "success": False,
            "message": "An error occurred while processing the request.",
            "error": str(e)
        }), 500

if __name__ == '__main__':
    threading.Thread(target=monitor_views_db, daemon=True).start()
    app.run(debug=True, host='0.0.0.0')
