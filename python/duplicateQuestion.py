import os
import re
import numpy as np
import pandas as pd
import threading
import time
from datetime import timedelta
import joblib
from functools import lru_cache
from flask import Flask, request, jsonify
from flask_cors import CORS
from dotenv import load_dotenv
from mysql.connector import pooling, Error
from sentence_transformers import SentenceTransformer
from sklearn.metrics.pairwise import cosine_similarity
from fuzzywuzzy import fuzz
from langdetect import detect
from langdetect.lang_detect_exception import LangDetectException
from Sastrawi.Stemmer.StemmerFactory import StemmerFactory 
from Sastrawi.StopWordRemover.StopWordRemoverFactory import StopWordRemoverFactory
import spacy 

app = Flask(__name__)
load_dotenv() 
CORS(app) 

text_model = None
stemmer_id = None
stopword_remover_id = None
nlp_en = None
classifier_model = None
existing_questions_data = [] 
question_embeddings = None
last_semantic_cache_refresh = None 

conn_pool = pooling.MySQLConnectionPool(
    pool_name="mypool",
    pool_size=10,
    host='127.0.0.1',
    user='root',
    password='', 
    database='uiux_project'
)


print("Memuat model AI untuk deteksi duplikat pertanyaan")
try:
    text_model = SentenceTransformer('paraphrase-multilingual-MiniLM-L12-v2')
    stemmer_id = StemmerFactory().create_stemmer()
    stopword_remover_id = StopWordRemoverFactory().create_stop_word_remover()
    
    try:
        nlp_en = spacy.load("en_core_web_sm")
    except IOError:
        nlp_en = None
        print("Model spaCy 'en_core_web_sm' tidak ditemukan. Lematisasi/stopwords teks Inggris mungkin tidak berfungsi.")

    classifier_model = joblib.load('duplicate_classifier_model.pkl')

except Exception as e:
    print("Detail Error: ", e)
    text_model = None
    stemmer_id = None
    stopword_remover_id = None
    nlp_en = None
    classifier_model = None

def fetch_from_db_duplicate(query, params=None):
    if conn_pool is None: return []
    conn = None
    try:
        conn = conn_pool.get_connection()
        cursor = conn.cursor(dictionary=True)
        cursor.execute(query, params or ())
        result = cursor.fetchall()
        cursor.close()
        return result
    except Error as e:
        print(f"Error pengambilan database (duplicate detector): {e}")
        return []
    finally:
        if conn and conn.is_connected():
            conn.close()

def simple_preprocess(text: str) -> str:
    if not isinstance(text, str): return ""
    text = text.lower()
    text = re.sub(r'<.*?>', '', text) # Menghapus tag HTML
    text = re.sub(r'\s+', ' ', text).strip() # Mengurangi spasi berlebihan
    return text

def detect_language(text: str) -> str:
    try:
        if text and len(text.strip()) > 10: return detect(text)
        return "unknown"
    except LangDetectException:
        return "unknown"

def advanced_multilingual_preprocess(text: str) -> str:
    """Stemming/lemmatisasi dan penghapusan stopword sesuai bahasa."""
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

def jaccard_similarity(list1, list2):
    """Hitung kemiripan Jaccard antara dua daftar kata."""
    s1 = set(list1)
    s2 = set(list2)
    if not s1 and not s2: return 0.0 
    return len(s1.intersection(s2)) / len(s1.union(s2))

def create_features_multilingual(q1_title: str, q1_question: str, q2_title: str, q2_question: str) -> dict:
    t1_clean = advanced_multilingual_preprocess(q1_title)
    q1_clean = advanced_multilingual_preprocess(q1_question)
    t2_clean = advanced_multilingual_preprocess(q2_title)
    q2_clean = advanced_multilingual_preprocess(q2_question)

    features = {}
    
    # --- Fitur Panjang Teks ---
    features['len_char_q1'] = len(q1_question)
    features['len_char_q2'] = len(q2_question)
    features['len_word_q1'] = len(q1_question.split())
    features['len_word_q2'] = len(q2_question.split())
    features['len_diff_word'] = abs(features['len_word_q1'] - features['len_word_q2']) / max(features['len_word_q1'], features['len_word_q2'], 1)

    # --- Fitur Kemiripan Semantik & Fuzzy ---
    try:
        if text_model is None:
            raise Exception("Model embedding teks tidak dimuat. Tidak dapat menghitung fitur semantik.")

        embeddings = text_model.encode([t1_clean, q1_clean, t2_clean, q2_clean], normalize_embeddings=True)
        t1_vec, q1_vec, t2_vec, q2_vec = embeddings[0], embeddings[1], embeddings[2], embeddings[3]
        
        fuzz_scores = [fuzz.QRatio(t1_clean, t2_clean)/100.0, 
                       fuzz.QRatio(q1_clean, q2_clean)/100.0, 
                       fuzz.QRatio(t1_clean, q2_clean)/100.0, 
                       fuzz.QRatio(q1_clean, t2_clean)/100.0]
        features['fuzz_avg'] = np.mean(fuzz_scores)
        features['fuzz_max'] = np.max(fuzz_scores)

        cosine_scores = [np.dot(t1_vec, t2_vec), np.dot(q1_vec, q2_vec), 
                         np.dot(t1_vec, q2_vec), np.dot(q1_vec, t2_vec)]
        features['cosine_avg'] = np.mean(cosine_scores)
        features['cosine_max'] = np.max(cosine_scores)
        
        cross_cosine_scores = [np.dot(t1_vec, q2_vec), np.dot(q1_vec, t2_vec)]
        features['cosine_cross_max'] = np.max(cross_cosine_scores)

        t1_tokens, q1_tokens, t2_tokens, q2_tokens = t1_clean.split(), q1_clean.split(), t2_clean.split(), q2_clean.split()
        features['jaccard_title'] = jaccard_similarity(t1_tokens, t2_tokens)
        features['jaccard_question'] = jaccard_similarity(q1_tokens, q2_tokens)
        cross_jaccard_1 = jaccard_similarity(t1_tokens, q2_tokens)
        cross_jaccard_2 = jaccard_similarity(q1_tokens, t2_tokens)
        features['jaccard_cross_avg'] = np.mean([cross_jaccard_1, cross_jaccard_2])

    except Exception as e:
        print(f"Error saat membuat fitur semantik/fuzzy: {e}. Mengisi dengan nol.")
        keys_to_zero = ['fuzz_avg', 'fuzz_max', 'cosine_avg', 'cosine_max', 'cosine_cross_max', 
                        'jaccard_title', 'jaccard_question', 'jaccard_cross_avg']
        for key in keys_to_zero:
            features[key] = 0.0

    return features

def build_semantic_cache():
    global existing_questions_data, question_embeddings, last_semantic_cache_refresh
    if text_model is None:
        print("Model embedding teks tidak dimuat. Cache semantik tidak dapat dibangun.")
        return

    query = "SELECT id, title, question FROM questions"
    raw_questions = fetch_from_db_duplicate(query) 

    existing_questions_data = raw_questions
    corpus = [f"{q.get('title', '')} {q.get('question', '')}" for q in raw_questions]
    question_embeddings = text_model.encode(corpus, show_progress_bar=False, normalize_embeddings=True)
    
    last_semantic_cache_refresh = time.time()

def find_top_semantic_candidates(title: str, question: str, top_n: int = 20) -> list:
    if question_embeddings is None or len(existing_questions_data) == 0 or text_model is None: 
        print("[INFO] Cache semantik tidak siap atau kosong. Tidak dapat menemukan kandidat.")
        return []

    input_title_embedding, input_question_embedding = text_model.encode(
        [simple_preprocess(title), simple_preprocess(question)],
        normalize_embeddings=True 
    )

    title_similarities = cosine_similarity([input_title_embedding], question_embeddings)[0]
    question_similarities = cosine_similarity([input_question_embedding], question_embeddings)[0]

    # Gabungkan kemiripan. Beri bobot lebih tinggi pada kemiripan pertanyaan
    combined_similarities = (0.3 * title_similarities) + (0.7 * question_similarities)

    top_indices = np.argsort(combined_similarities)[-top_n:][::-1]
    
    candidates = []
    for i in top_indices:
        q_data = existing_questions_data[i]
        candidates.append({
            'existing_question_id': q_data.get('id'),
            'existing_question_title': q_data.get('title', ''),
            'existing_question_text': q_data.get('question', ''),
            'semantic_similarity_score': round(float(combined_similarities[i]), 4)
        })
    return candidates

@app.route('/ai/detect-duplicate', methods=['POST'])
def detect_duplicate_hybrid_api():
    if classifier_model is None or text_model is None:
        return jsonify({'success': False, 'message': "Model AI untuk deteksi duplikat tidak siap. Silakan cek log server."}), 503

    title = request.form.get('title', '')
    question_text = request.form.get('question', '')

    if not title and not question_text:
        return jsonify({'success': False, 'message': "Judul atau teks pertanyaan harus disediakan."}), 400

    PROBABILITY_THRESHOLD = 0.5 

    try:
        top_candidates = find_top_semantic_candidates(title, question_text, top_n=20)
        
        if not top_candidates:
            return jsonify({'success': True, 'message': 'Tidak ditemukan pertanyaan yang mirip secara semantik di database.', 'duplicates': []}), 200

        potential_duplicates = []
        for candidate in top_candidates:
            candidate_title = candidate['existing_question_title']
            candidate_question = candidate['existing_question_text']
            
            features_dict = create_features_multilingual(title, question_text, candidate_title, candidate_question)
            
            expected_features_order = [
                'len_char_q1', 'len_char_q2', 'len_word_q1', 'len_word_q2', 'len_diff_word',
                'fuzz_avg', 'fuzz_max', 'cosine_avg', 'cosine_max', 'cosine_cross_max',
                'jaccard_title', 'jaccard_question', 'jaccard_cross_avg'
            ]
            
            feature_values = [features_dict.get(col, 0.0) for col in expected_features_order]
            features_df = pd.DataFrame([feature_values], columns=expected_features_order)

            probability = classifier_model.predict_proba(features_df)[:, 1][0] 
            
            # Cek hasil sesuai akal sehat
            cosine_max = features_dict.get('cosine_max', 0)
            jaccard_max = max(features_dict.get('jaccard_title', 0), features_dict.get('jaccard_question', 0))

            MIN_COSINE_THRESHOLD = 0.60 
            MIN_JACCARD_THRESHOLD = 0.15

            if probability > PROBABILITY_THRESHOLD:
                if cosine_max > MIN_COSINE_THRESHOLD or jaccard_max > MIN_JACCARD_THRESHOLD:
                    candidate['duplication_probability'] = round(float(probability), 4)
                    potential_duplicates.append(candidate)
                else:
                    print(f"PREDIKSI DITOLAK (Prob: {probability:.2f}) untuk '{candidate_title}' karena skor dasar terlalu rendah (Cosine: {cosine_max:.2f}, Jaccard: {jaccard_max:.2f})")

        potential_duplicates.sort(key=lambda x: x['duplication_probability'], reverse=True)

        return jsonify({
            'success': True,
            'message': f"Ditemukan {len(potential_duplicates)} potensi duplikat yang akurat.",
            'duplicates': potential_duplicates
        }), 200

    except Exception as e:
        print(f"Error di endpoint /detect-duplicate: {e}")
        import traceback
        traceback.print_exc() 
        return jsonify({'success': False, 'message': "Terjadi kesalahan server internal selama deteksi duplikat."}), 500

def periodic_cache_refresh(interval_seconds: int = 3600):
    while True:
        time.sleep(interval_seconds)
        try:
            build_semantic_cache()
        except Exception as e:
            print(f"ERROR: Gagal merefresh cache semantik: {e}")
            import traceback
            traceback.print_exc()

if __name__ == '__main__':

    build_semantic_cache()

    refresh_interval = int(os.getenv('DUPLICATE_CACHE_REFRESH_INTERVAL_SECONDS', 3600))
    threading.Thread(target=periodic_cache_refresh, args=(refresh_interval,), daemon=True).start()
    
    app.run(debug=True, host='0.0.0.0', use_reloader=False)