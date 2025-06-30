import re
import numpy as np
from fuzzywuzzy import fuzz
from langdetect import detect
from langdetect.lang_detect_exception import LangDetectException

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

def multilingual_preprocess(text: str, nlp_en=None, stemmer_id=None, stopword_remover_id=None) -> str:
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
    s1 = set(list1)
    s2 = set(list2)
    if not s1 and not s2: return 0.0
    return len(s1.intersection(s2)) / len(s1.union(s2))

def create_features_from_embeddings(
    q1_title, q1_question, q1_text_emb, q1_img_emb,
    q2_title, q2_question, q2_text_emb, q2_img_emb,
    nlp_en=None, stemmer_id=None, stopword_remover_id=None
):
    t1_clean = multilingual_preprocess(q1_title, nlp_en, stemmer_id, stopword_remover_id)
    q1_clean = multilingual_preprocess(q1_question, nlp_en, stemmer_id, stopword_remover_id)
    t2_clean = multilingual_preprocess(q2_title, nlp_en, stemmer_id, stopword_remover_id)
    q2_clean = multilingual_preprocess(q2_question, nlp_en, stemmer_id, stopword_remover_id)
    
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