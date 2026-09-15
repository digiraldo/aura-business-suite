import json
import openpyxl
from openpyxl.utils import get_column_letter

path = r"Y-Global2 2017-20 (Use this one).xlsx"
wb = openpyxl.load_workbook(path, data_only=True)

TARGETS = ["Oper Flow 25 ", "Oper Flow 26 ", "Cap Sum Assets", "US $ details", "ENE25", "DIC25", "ENE26", "ABR26"]

for name in TARGETS:
    if name not in wb.sheetnames:
        print(f"\n=== {name} (NO EXISTE) ===")
        continue
    ws = wb[name]
    print(f"\n=== {name} ===")
    print(f"rows={ws.max_row} cols={ws.max_column}")

    # detect used range rough in first 120 rows/40 cols
    max_r = min(ws.max_row, 140)
    max_c = min(ws.max_column, 40)

    # print rows with any content
    printed = 0
    for r in range(1, max_r + 1):
        vals = []
        has = False
        for c in range(1, max_c + 1):
            v = ws.cell(r, c).value
            if v is not None and str(v).strip() != "":
                has = True
            vals.append(v)
        if has:
            # compact show first 12 cols non-empty-ish
            out = []
            for c in range(1, min(max_c, 16) + 1):
                v = ws.cell(r, c).value
                if v is not None and str(v).strip() != "":
                    out.append(f"{get_column_letter(c)}={v}")
            if out:
                print(f"R{r}: " + " | ".join(out))
                printed += 1
        if printed >= 45:
            break
