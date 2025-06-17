import pandas as pd
import numpy as np
import joblib
import os
import re
from tqdm import tqdm
import xgboost as xgb
from sklearn.model_selection import GroupShuffleSplit
from sklearn.metrics import accuracy_score, classification_report
from dotenv import load_dotenv
from mysql.connector import pooling, Error

from sentence_transformers import SentenceTransformer
from Sastrawi.Stemmer.StemmerFactory import StemmerFactory
from Sastrawi.StopWordRemover.StopWordRemoverFactory import StopWordRemoverFactory
import spacy
from langdetect import detect
from langdetect.lang_detect_exception import LangDetectException
from fuzzywuzzy import fuzz

load_dotenv()
print("Loading universal models for retraining...")
text_model = SentenceTransformer('paraphrase-multilingual-MiniLM-L12-v2')
stemmer_id = StemmerFactory().create_stemmer()
stopword_remover_id = StopWordRemoverFactory().create_stop_word_remover()
nlp_en = spacy.load("en_core_web_sm")
print("Models loaded.")

db_pool = pooling.MySQLConnectionPool(
    pool_name="retrain_pool",
    pool_size=2,
    host=os.getenv('DB_HOST', '127.0.0.1'),
    user=os.getenv('DB_USERNAME', 'root'),
    password=os.getenv('DB_PASSWORD', ''),
    database=os.getenv('DB_DATABASE', 'uiux_project')
)

def fetch_db_data(query, params=None):
    conn = db_pool.get_connection()
    cursor = conn.cursor(dictionary=True)
    cursor.execute(query, params or ())
    result = cursor.fetchall()
    cursor.close()
    conn.close()
    return result

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
    if lang == 'id':
        stemmed_text = stemmer_id.stem(cleaned_text)
        return stopword_remover_id.remove(stemmed_text)
    elif lang == 'en':
        doc = nlp_en(cleaned_text)
        tokens = [token.lemma_ for token in doc if not token.is_stop and not token.is_punct]
        return " ".join(tokens)
    else:
        return cleaned_text

def jaccard_similarity(list1, list2):
    s1 = set(list1)
    s2 = set(list2)
    if not s1 and not s2: return 0.0
    return len(s1.intersection(s2)) / len(s1.union(s2))

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


def fetch_all_labeled_pairs():
    print("Fetching all labeled pairs from the database...")
    query = "SELECT question1_id, question2_id, is_duplicate FROM labeled_duplicate_pairs"
    return fetch_db_data(query)

def fetch_question_data(question_id):
    query = """
        SELECT q.title, q.question, qe.text_embedding, qe.image_embedding
        FROM questions q
        LEFT JOIN question_embeddings qe ON q.id = qe.question_id
        WHERE q.id = %s
    """
    results = fetch_db_data(query, (question_id,))
    if not results:
        return None
    
    data = results[0]
    if data.get('text_embedding'):
        data['text_embedding'] = np.frombuffer(data['text_embedding'], dtype=np.float32)
    if data.get('image_embedding'):
        data['image_embedding'] = np.frombuffer(data['image_embedding'], dtype=np.float32)
        
    return data


def main_retrain():
    labeled_pairs = fetch_all_labeled_pairs()
    if not labeled_pairs:
        print("No labeled pairs found. Exiting.")
        return
    print(f"Found {len(labeled_pairs)} labeled pairs for training.")

    features_list = []
    labels = []
    groups = []
    
    question_data_cache = {}

    for pair in tqdm(labeled_pairs, desc="Processing pairs and creating features"):
        q1_id = pair['question1_id']
        q2_id = pair['question2_id']

        if q1_id not in question_data_cache:
            question_data_cache[q1_id] = fetch_question_data(q1_id)
        
        if q2_id not in question_data_cache:
            question_data_cache[q2_id] = fetch_question_data(q2_id)

        q1_data = question_data_cache[q1_id]
        q2_data = question_data_cache[q2_id]

        if not q1_data or not q2_data:
            print(f"Skipping pair ({q1_id}, {q2_id}) due to missing data.")
            continue
        
        features = create_features_from_embeddings(
            q1_data['title'], q1_data['question'], q1_data.get('text_embedding'), q1_data.get('image_embedding'),
            q2_data['title'], q2_data['question'], q2_data.get('text_embedding'), q2_data.get('image_embedding')
        )
        features_list.append(features)
        labels.append(pair['is_duplicate'])
        groups.append(q1_id)

    df_features = pd.DataFrame(features_list)
    y = pd.Series(labels)
    
    print(f"Feature creation complete. Shape of feature matrix: {df_features.shape}")

    gss = GroupShuffleSplit(n_splits=1, test_size=0.2, random_state=42)
    train_idx, test_idx = next(gss.split(df_features, y, groups=groups))

    X_train, X_test = df_features.iloc[train_idx], df_features.iloc[test_idx]
    y_train, y_test = y.iloc[train_idx], y.iloc[test_idx]
    
    print(f"Data split: {len(X_train)} training samples, {len(X_test)} testing samples.")

    print("Training new XGBoost model...")
    model = xgb.XGBClassifier(
        objective='binary:logistic',
        eval_metric='logloss',
        n_estimators=200,
        max_depth=5,
        random_state=42,
        use_label_encoder=False
    )
    model.fit(X_train, y_train)
    print("Model training complete.")

    print("\n--- New Model Evaluation ---")
    y_pred = model.predict(X_test)
    print(classification_report(y_test, y_pred))
    print(f"Accuracy: {accuracy_score(y_test, y_pred):.4f}")

    model_filename = 'duplicate_classifier_model.pkl'
    joblib.dump(model, model_filename)
    print(f"\nSUCCESS: New model has been trained and saved as '{model_filename}'")
    print("--- Retraining Pipeline Finished ---")

if __name__ == '__main__':
    main_retrain()