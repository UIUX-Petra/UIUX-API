import joblib

# Pastikan nama file ini sesuai dengan file model Anda
MODEL_FILENAME = 'duplicate_classifier_model.pkl'

try:
    # Memuat model dari file .pkl
    model = joblib.load(MODEL_FILENAME)
    
    # Model scikit-learn yang lebih baru menyimpan nama fitur di atribut .feature_names_in_
    # Ini adalah cara paling andal untuk mendapatkan urutan yang benar
    if hasattr(model, 'feature_names_in_'):
        correct_feature_order = model.feature_names_in_
        print("Urutan fitur yang BENAR adalah:")
        print(list(correct_feature_order))
    else:
        print("Model ini tidak memiliki atribut 'feature_names_in_'.")
        print("Pastikan Anda menggunakan scikit-learn versi yang sama saat training dan saat menjalankan Flask.")
        print("Atau coba periksa kembali notebook/script training Anda untuk melihat urutan kolom DataFrame yang digunakan.")

except FileNotFoundError:
    print(f"Error: File '{MODEL_FILENAME}' tidak ditemukan.")
except Exception as e:
    print(f"Terjadi error saat memuat atau memeriksa model: {e}")