import mysql.connector
from flask import Flask, request, jsonify

app = Flask(__name__)

class Node:
    def __init__(self, user_id):
        self.user_id = user_id
        self.following = {}

    def follow(self, other_user, weight=1):
        self.following[other_user] = weight


class Graph:
    def __init__(self):
        self.nodes = {}

    def add_user(self, user_id):
        if user_id not in self.nodes:
            self.nodes[user_id] = Node(user_id)

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
        return [(candidate.user_id, score) for candidate, score in sorted_candidates[:top_n]]


graph = Graph()

def build_graph_from_db():
    conn = mysql.connector.connect(
        host='127.0.0.1',
        user='root',
        password='',
        database='tekweb_project'
    )
    cursor = conn.cursor()

    cursor.execute('SELECT follower_id, followed_id FROM follows')
    rows = cursor.fetchall()

    for follower_id, followed_id in rows:
        graph.add_follow(follower_id, followed_id)

    conn.close()

@app.before_request
def initialize_graph():
    build_graph_from_db()

@app.route('/recommend', methods=['GET'])
def recommend_api():
    try:
        user_id = request.args.get('user')
        
        if not user_id:
            return jsonify({
                "code": 400,
                "success": False,
                "message": "User ID is required.",
                "data": None
            }), 400

        if user_id not in graph.nodes:
            return jsonify({
                "code": 404,
                "success": False,
                "message": "User not found.",
                "data": None
            }), 404

        recommendations = graph.recommend_users(user_id)
        return jsonify({
            "code": 200,
            "success": True,
            "message": "Recommendations retrieved successfully.",
            "data": recommendations
        })

    except Exception as e:
        return jsonify({
            "code": 500,
            "success": False,
            "message": "An error occurred while processing the request.",
            "error": str(e)
        }), 500

if __name__ == '__main__':
    app.run(debug=True, host='0.0.0.0')