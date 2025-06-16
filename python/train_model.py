import pandas as pd
import numpy as np
import re
import joblib
import os
from tqdm import tqdm
from sentence_transformers import SentenceTransformer
from Sastrawi.Stemmer.StemmerFactory import StemmerFactory
from Sastrawi.StopWordRemover.StopWordRemoverFactory import StopWordRemoverFactory
import spacy
from langdetect import detect
from langdetect.lang_detect_exception import LangDetectException
from fuzzywuzzy import fuzz
import xgboost as xgb
from sklearn.model_selection import train_test_split
from sklearn.metrics import accuracy_score, precision_score, recall_score, roc_auc_score
from dotenv import load_dotenv
from sklearn.model_selection import GroupShuffleSplit

load_dotenv()

try:
    text_model = SentenceTransformer('paraphrase-multilingual-MiniLM-L12-v2')
    stemmer_id = StemmerFactory().create_stemmer()
    stopword_remover_id = StopWordRemoverFactory().create_stop_word_remover()
    nlp_en = spacy.load("en_core_web_sm")
except Exception as e:
    print(f"ERROR: Detail: {e}")
    exit()

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
    """Helper function to calculate Jaccard similarity between two lists of words."""
    s1 = set(list1)
    s2 = set(list2)
    if not s1 and not s2: return 0.0
    return len(s1.intersection(s2)) / len(s1.union(s2))

def features_multilingual(q1_title, q1_question, q2_title, q2_question):
    t1_clean = multilingual_preprocess(q1_title)
    q1_clean = multilingual_preprocess(q1_question)
    t2_clean = multilingual_preprocess(q2_title)
    q2_clean = multilingual_preprocess(q2_question)

    features = {}
    
    features['len_char_q1'] = len(q1_question)
    features['len_char_q2'] = len(q2_question)
    features['len_word_q1'] = len(q1_question.split())
    features['len_word_q2'] = len(q2_question.split())
    # Fitur perbedaan panjang, dinormalisasi agar nilainya kecil
    features['len_diff_word'] = abs(features['len_word_q1'] - features['len_word_q2']) / max(features['len_word_q1'], features['len_word_q2'], 1)

    try:
        embeddings = text_model.encode([t1_clean, q1_clean, t2_clean, q2_clean], normalize_embeddings=True)
        t1_vec, q1_vec, t2_vec, q2_vec = embeddings[0], embeddings[1], embeddings[2], embeddings[3]
        
        fuzz_scores = [fuzz.QRatio(t1_clean, t2_clean)/100.0, fuzz.QRatio(q1_clean, q2_clean)/100.0, fuzz.QRatio(t1_clean, q2_clean)/100.0, fuzz.QRatio(q1_clean, t2_clean)/100.0]
        features['fuzz_avg'] = np.mean(fuzz_scores)
        features['fuzz_max'] = np.max(fuzz_scores)

        cosine_scores = [np.dot(t1_vec, t2_vec), np.dot(q1_vec, q2_vec), np.dot(t1_vec, q2_vec), np.dot(q1_vec, t2_vec)]
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

    except Exception:
        # Jika ada error (misal teks kosong), isi fitur lain dengan 0
        keys_to_zero = ['fuzz_avg', 'fuzz_max', 'cosine_avg', 'cosine_max', 'cosine_cross_max', 'jaccard_title', 'jaccard_question', 'jaccard_cross_avg']
        for key in keys_to_zero:
            features[key] = 0.0

    return features


def load_training_data():
    laravel_project_path = os.getenv('LARAVEL_PROJECT_PATH', 'C:/laragon/www/UIUX-API')
    csv_file_path = os.path.join(laravel_project_path, 'storage', 'app', 'ml_data', 'labeled_pairs.csv')
    
    try:
        df_labeled = pd.read_csv(csv_file_path)
        print(f"Berhasil membaca '{csv_file_path}'. Total: {len(df_labeled)} pasangan.")
        return df_labeled
    except FileNotFoundError:
        print(f"[ERROR] File '{csv_file_path}' tidak ditemukan.")
        return pd.DataFrame()

def main():
    # Langkah 1: Muat data training
    df_labeled = load_training_data()
    if df_labeled.empty:
        return

    # Langkah 2: Buat fitur dari data
    print("Membuat fitur multibahasa untuk semua pasangan")
    df_labeled.dropna(subset=['q1_title', 'q1_question', 'q2_title', 'q2_question'], inplace=True)
    
    tqdm.pandas(desc="Processing Features")
    features_list = df_labeled.progress_apply(
        lambda row: features_multilingual(row['q1_title'], row['q1_question'], row['q2_title'], row['q2_question']), 
        axis=1
    )
    df_features = pd.json_normalize(features_list)

    # Langkah 3: Siapkan data X dan y
    X = df_features
    y = df_labeled['is_duplicate']

    groups = df_labeled['group_id']
    gss = GroupShuffleSplit(n_splits=1, test_size=0.2, random_state=42)
    
    train_idx, test_idx = next(gss.split(X, y, groups=groups))

    X_train, X_test = X.iloc[train_idx], X.iloc[test_idx]
    y_train, y_test = y.iloc[train_idx], y.iloc[test_idx]
    
    print(f"Data dibagi: {len(X_train)} untuk training, {len(X_test)} untuk testing.")

    # Langkah 4: Latih model XGBoost
    model = xgb.XGBClassifier(objective='binary:logistic', eval_metric='logloss', n_estimators=200, max_depth=5, random_state=42)
    model.fit(X_train, y_train)

    # Langkah 5: Evaluasi model
    y_pred_proba = model.predict_proba(X_test)[:, 1]
    y_pred = (y_pred_proba >= 0.5).astype(int)

    print(f"Akurasi: {accuracy_score(y_test, y_pred):.3f}")
    print(f"Presisi: {precision_score(y_test, y_pred):.3f}")
    print(f"Recall: {recall_score(y_test, y_pred):.3f}")
    print(f"AUC-ROC: {roc_auc_score(y_test, y_pred_proba):.3f}")

    # Langkah 6: Simpan model yang sudah dilatih
    joblib.dump(model, 'duplicate_classifier_model.pkl')
    print("\nModel berhasil dilatih dan disimpan sebagai 'duplicate_classifier_model.pkl'")

if __name__ == '__main__':
    main()