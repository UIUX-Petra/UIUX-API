import pandas as pd
import numpy as np
import joblib
import os
import re
from tqdm import tqdm
import xgboost as xgb
from sklearn.model_selection import GroupShuffleSplit
from sklearn.metrics import classification_report, f1_score
from dotenv import load_dotenv
from mysql.connector import pooling
from shared_features import create_features_from_embeddings
from Sastrawi.Stemmer.StemmerFactory import StemmerFactory
from Sastrawi.StopWordRemover.StopWordRemoverFactory import StopWordRemoverFactory
import spacy

load_dotenv()

def fetch_db_data(db_pool, query, params=None):
    conn = db_pool.get_connection()
    cursor = conn.cursor(dictionary=True)
    cursor.execute(query, params or ())
    result = cursor.fetchall()
    cursor.close()
    conn.close()
    return result

MODEL_PATH = 'duplicate_classifier_model.pkl'
TEMP_MODEL_PATH = 'duplicate_classifier_model.pkl.tmp'
MIN_F1_THRESHOLD = 0.7

def archive_old_model(model_path_to_archive):
    """
    Checks if a model exists at the given path and renames it to the next
    available version number (e.g., _1, _2, etc.).
    """
    if not os.path.exists(model_path_to_archive):
        print("No old model found to archive. Skipping.")
        return

    print(f"Found old model at '{model_path_to_archive}'. Archiving it...")
    
    directory = os.path.dirname(model_path_to_archive) or '.'
    base_name = os.path.basename(model_path_to_archive).replace('.pkl', '')
    
    archive_pattern = re.compile(rf"^{re.escape(base_name)}_(\d+)\.pkl$")
    
    versions = []
    for filename in os.listdir(directory):
        match = archive_pattern.match(filename)
        if match:
            versions.append(int(match.group(1)))
            
    next_version = (max(versions) + 1) if versions else 1
    
    archive_filename = f"{base_name}_{next_version}.pkl"
    archive_path = os.path.join(directory, archive_filename)
    
    try:
        os.rename(model_path_to_archive, archive_path)
        print(f"SUCCESS: Old model archived to '{archive_path}'")
    except OSError as e:
        print(f"ERROR: Could not archive old model: {e}")


def safe_model_retrain():
    print("--- Starting Daily Model Retraining Task ---")
    
    try:
        stemmer_id = StemmerFactory().create_stemmer()
        stopword_remover_id = StopWordRemoverFactory().create_stop_word_remover()
        nlp_en = spacy.load("en_core_web_sm")
        db_pool = pooling.MySQLConnectionPool(
            pool_name="retrain_pool", pool_size=2,
            host=os.getenv('DB_HOST', '127.0.0.1'), user=os.getenv('DB_USERNAME', 'root'),
            password=os.getenv('DB_PASSWORD', ''), database=os.getenv('DB_DATABASE', 'uiux_project')
        )
        print("Retraining models and DB connection loaded.")
    except Exception as e:
        print(f"ERROR: Could not initialize models or DB for retraining: {e}")
        return

    labeled_pairs = fetch_all_labeled_pairs(db_pool)
    if len(labeled_pairs) < 50:
        print(f"Not enough new data ({len(labeled_pairs)} pairs). Skipping retraining.")
        return
        
    features_list, labels, groups = [], [], []
    question_data_cache = {}

    for pair in tqdm(labeled_pairs, desc="Creating training features"):
        q1_id, q2_id = pair['question1_id'], pair['question2_id']
        
        for q_id in [q1_id, q2_id]:
            if q_id not in question_data_cache:
                question_data_cache[q_id] = fetch_question_data(db_pool, q_id)

        q1_data, q2_data = question_data_cache[q1_id], question_data_cache[q2_id]
        if not q1_data or not q2_data: continue

        features = create_features_from_embeddings(
            q1_data['title'], q1_data['question'], q1_data.get('text_embedding'), q1_data.get('image_embedding'),
            q2_data['title'], q2_data['question'], q2_data.get('text_embedding'), q2_data.get('image_embedding'),
            nlp_en=nlp_en, stemmer_id=stemmer_id, stopword_remover_id=stopword_remover_id
        )
        features_list.append(features)
        labels.append(pair['is_duplicate'])
        groups.append(q1_id)

    df_features = pd.DataFrame(features_list)
    y = pd.Series(labels)
    gss = GroupShuffleSplit(n_splits=1, test_size=0.2, random_state=42)
    train_idx, test_idx = next(gss.split(df_features, y, groups=groups))
    X_train, X_test = df_features.iloc[train_idx], df_features.iloc[test_idx]
    y_train, y_test = y.iloc[train_idx], y.iloc[test_idx]
    
    print("Training new XGBoost model...")
    model = xgb.XGBClassifier(objective='binary:logistic', eval_metric='logloss', use_label_encoder=False)
    model.fit(X_train, y_train)

    print("\n--- New Model Evaluation ---")
    y_pred = model.predict(X_test)
    new_f1 = f1_score(y_test, y_pred)
    print(classification_report(y_test, y_pred))
    print(f"New Model F1 Score: {new_f1:.4f}")

    if new_f1 >= MIN_F1_THRESHOLD:
        print(f"New model F1 score ({new_f1:.4f}) meets threshold.")
        
        archive_old_model(MODEL_PATH)
        
        print(f"Saving new model to '{MODEL_PATH}'...")
        joblib.dump(model, TEMP_MODEL_PATH)
        os.replace(TEMP_MODEL_PATH, MODEL_PATH)
        print(f"SUCCESS: New model has been trained and saved.")
    else:
        print(f"FAIL: New model F1 score ({new_f1:.4f}) is below threshold of {MIN_F1_THRESHOLD}. Keeping old model.")

    print("--- Retraining Pipeline Finished ---")


def fetch_all_labeled_pairs(db_pool):
    print("Fetching all labeled pairs from the database...")
    query = "SELECT question1_id, question2_id, is_duplicate FROM labeled_duplicate_pairs"
    return fetch_db_data(db_pool, query)

def fetch_question_data(db_pool, question_id):
    query = """
        SELECT q.title, q.question, qe.text_embedding, qe.image_embedding
        FROM questions q
        LEFT JOIN question_embeddings qe ON q.id = qe.question_id
        WHERE q.id = %s
    """
    results = fetch_db_data(db_pool, query, (question_id,))
    if not results: return None
    data = results[0]
    if data.get('text_embedding'): data['text_embedding'] = np.frombuffer(data['text_embedding'], dtype=np.float32)
    if data.get('image_embedding'): data['image_embedding'] = np.frombuffer(data['image_embedding'], dtype=np.float32)
    return data

if __name__ == '__main__':
    safe_model_retrain()