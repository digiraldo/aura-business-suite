import csv
from datetime import datetime

src = r"import_transacciones_2026_completo.csv"
out_actual = r"import_transacciones_2026_actual_ene_abr.csv"
out_budget = r"import_transacciones_2026_presupuesto_may_dic.csv"

with open(src, "r", encoding="utf-8", newline="") as f:
    rows = list(csv.DictReader(f))
    headers = rows[0].keys() if rows else [
        "transaction_date","transaction_type","category","amount","description","notes","payment_method","reference_number"
    ]

actual_rows = []
budget_rows = []

for r in rows:
    d = r.get("transaction_date", "").strip()
    desc = (r.get("description", "") or "").lower()

    try:
        month = datetime.strptime(d, "%d/%m/%Y").month
    except Exception:
        continue

    # Archivo 1: solo reales de enero-abril
    if 1 <= month <= 4 and "[actual]" in desc:
        actual_rows.append(r)

    # Archivo 2: presupuesto/proyección de mayo-diciembre
    if 5 <= month <= 12:
        # forzamos que queden solo líneas de presupuesto/proyección
        if "[presupuesto]" in desc or "[actual]" not in desc:
            budget_rows.append(r)

with open(out_actual, "w", encoding="utf-8", newline="") as f:
    w = csv.DictWriter(f, fieldnames=list(headers))
    w.writeheader()
    w.writerows(actual_rows)

with open(out_budget, "w", encoding="utf-8", newline="") as f:
    w = csv.DictWriter(f, fieldnames=list(headers))
    w.writeheader()
    w.writerows(budget_rows)

print(f"actual_ene_abr_rows={len(actual_rows)} file={out_actual}")
print(f"presupuesto_may_dic_rows={len(budget_rows)} file={out_budget}")
