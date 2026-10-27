import os
import time
import pandas as pd
import requests

# 1. Konfigurasi dari cURL resmi Anda
TOKEN = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIwNzlmNGZkZC0wZDdjLTRiNjUtOTI4OC02ODdhMWYyZTE4NWYiLCJpc3MiOiJodHRwOlwvXC9zaXN0ZXItYXBpLmtlbWRpa3Rpc2FpbnRlay5nby5pZFwvd3Mtc2FuZGJveC5waHBcLzEuMFwvYXV0aG9yaXplIiwiaWF0IjoxNzkwOTMyOTk1LCJleHAiOjE3OTA5MzY1OTUsIm5iZiI6MTc5MDkzMjk5NSwianRpIjoiNmY2OTI2YTE1ZDJmOGFjMSJ9.ogikq7tcHH_5yrCdMvwsP733nPSxZ_cKgTvBnsdiPxs"
ID_SP = "7030c216-d878-4a7c-bc7d-5f58473a50aa"
URL = "https://sister-api.kemdiktisaintek.go.id/ws-sandbox.php/1.0/referensi/sdm"

# Header disesuaikan dengan cURL + User-Agent browser agar tidak diblokir Cloudflare/WAF
headers = {
    "Accept": "application/json",
    "Authorization": f"Bearer {TOKEN.strip()}",
    "User-Agent": (
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML,"
        " like Gecko) Chrome/122.0.0.0 Safari/537.36"
    ),
}

# 2. Buka data dosen
excel_file = "rekap-data-honor-part-timer.xlsx"
df = pd.read_excel(excel_file, sheet_name=0)
print(f"Total data dosen: {len(df)}")

id_sdm_list = []

# Gunakan session agar koneksi TCP/SSL lebih stabil dan cepat
session = requests.Session()
session.headers.update(headers)

for idx, row in df.iterrows():
  nama_dosen = str(row["Nama"]).strip()

  # Cukup kirim id_sp dan nama (minimal 3 karakter)
  params = {"id_sp": ID_SP, "nama": nama_dosen}

  found_id = ""
  try:
    response = session.get(URL, params=params, timeout=15)

    if response.status_code == 200:
      res_json = response.json()
      # Respon SISTER bisa berupa list langsung atau objek dengan key 'data'
      records = (
          res_json if isinstance(res_json, list) else res_json.get("data", [])
      )

      if records and len(records) > 0:
        first_item = records[0] if isinstance(records, list) else records
        found_id = first_item.get("id_sdm", "")
        print(f"[{idx+1}/{len(df)}] SUKSES: {nama_dosen} -> {found_id}")
      else:
        print(f"[{idx+1}/{len(df)}] DATA KOSONG: {nama_dosen}")
    elif response.status_code == 401:
      print("\n[ERROR 401] Token SISTER kedaluwarsa! Minta token baru.")
      break
    elif response.status_code == 403:
      print(
          f"\n[ERROR 403] Akses ditolak. Cuplikan respon: {response.text[:200]}"
      )
      break
    else:
      print(f"[{idx+1}/{len(df)}] HTTP {response.status_code}: {nama_dosen}")

  except Exception as e:
    print(f"[{idx+1}/{len(df)}] ERROR KONEKSI: {nama_dosen} ({e})")

  id_sdm_list.append(found_id)
  time.sleep(0.3)  # Jeda 300 ms agar aman dari limit server

# 3. Simpan hasil akhir
if len(id_sdm_list) == len(df):
  df["id_sdm"] = id_sdm_list
  output_file = "rekap_honor_dengan_id_sdm.xlsx"
  df.to_excel(output_file, index=False)
  print(f"\nSelesai! Seluruh data tersimpan di: {output_file}")