"""
============================================================
  EXTRACTOR DE DATOS FINANCIEROS 2026 → Aura Business Suite
============================================================
Lee las hojas ENE26, FEB26, Mar26, ABR26 del archivo Excel,
extrae cada transaccion, la clasifica por categoria y tipo
(ingreso/egreso), compara con el Oper Flow 26 para generar
un cuadro de Presupuesto vs Actual, y exporta todo al
formato CSV de importacion de Aura Financial.

Autor: Aura Dev Team
Fecha: 2026-05-15
"""

import openpyxl
import csv
import re
import os
import warnings
from datetime import datetime
from collections import defaultdict

warnings.filterwarnings("ignore", category=UserWarning)

# ─────────────────────────────────────────────────────────────
# CONFIGURACION
# ─────────────────────────────────────────────────────────────
EXCEL_FILE = r'Y-Global2 2017-20 (Use this one).xlsx'
OUTPUT_CSV  = 'plantilla-transacciones-2026.csv'

# Hojas a procesar: nombre_hoja -> (numero_mes, nombre_mes_largo)
SHEETS = {
    'ENE26': (1, 'Enero'),
    'FEB26': (2, 'Febrero'),
    'MAR26': (3, 'Marzo'),
    'ABR26': (4, 'Abril'),
}

# Columnas de categorias (Q=17 a BC=55) - fila 3 del Excel
# Clasificacion de grupos segun instrucciones del usuario
COL_RANGES = {
    'income':   (17, 24),   # Q3:X3 = Ingresos
    'fondo_op': (25, 37),   # Y3:AK3 = Fondo de Operaciones
    'fondo_pr': (38, 43),   # AL3:AQ3 = Fondo de Propiedad
    'gastos_k': (44, 44),   # AR3 = Gastos Capital (excluido de egresos normales)
    'cap_had':  (45, 52),   # AS3:AZ3 = Capital y HADIME
    'vehiculo': (53, 55),   # BA3:BC3 = Vehiculo (antes "Van")
}

# Mapeo de mes corto (usado en la columna B) a numero de mes
MONTH_CODE = {
    'E': 1, 'F': 2, 'M': 3, 'A': 4,
    'D': 12, 'J': 6, 'S': 9, 'O': 10, 'N': 11,
}


# ─────────────────────────────────────────────────────────────
# FUNCIONES DE PARSING
# ─────────────────────────────────────────────────────────────

def parse_date(date_str, month_num, year=2026):
    """
    Convierte 'E 2', 'D 4', 'F 15' etc. a un objeto datetime.
    El numero de mes real viene de la hoja (no de la letra).
    """
    if not date_str or not isinstance(date_str, str):
        return None
    
    # Limpiar espacios
    date_str = date_str.strip()
    
    # Patron: LETRA ESPACIO(S) NUMERO
    match = re.match(r'[A-Za-z]+\s+(\d+)', date_str)
    if match:
        day = int(match.group(1))
        try:
            return datetime(year, month_num, day)
        except ValueError:
            return None
    return None


def get_description_from_row(ws, row_num):
    """
    Extrae la descripcion de las columnas BD4:BM4 (cols 56-65).
    Busca en la fila de fecha (impar) ya que ahi estan las notas.
    """
    descriptions = []
    for col in range(56, 66):  # BD=56 a BM=65
        v = ws.cell(row=row_num, column=col).value
        if v is not None and str(v).strip():
            val = str(v).strip()
            # Columna BD es "N-F" (factura), no es descripcion
            if col == 56 and val in ('F', 'N', 'NF', 'N-F'):
                continue
            descriptions.append(val)
    return ' | '.join(descriptions) if descriptions else ''


def normalize_category_name(name):
    """
    Normaliza el nombre de una categoria leida del Excel:
    - Elimina palabras partidas con guión (ej. 'Manteni- miento' -> 'Mantenimiento')
    - Normaliza 'Gastos de CAPITAL' -> 'Gastos de Capital'
    - Elimina espacios redundantes
    """
    import re as _re
    if not name:
        return name

    # 1. Eliminar saltos de línea y normalizar espacios
    name = name.replace('\n', ' ').replace('\r', ' ')

    # 2. Unir palabras partidas con guión: 'Manteni- miento' -> 'Mantenimiento'
    #    Patron: letra(s) + guion + espacio(s) + letra(s)
    name = _re.sub(r'(\w)-\s+(\w)', r'\1\2', name)

    # 3. Eliminar espacios múltiples
    name = _re.sub(r'\s+', ' ', name).strip()

    # 4. Tabla de normalizaciones específicas del Excel
    REPLACEMENTS = {
        'Gastos de CAPITAL': 'Gastos de Capital',
        'GASTOS DE CAPITAL': 'Gastos de Capital',
        'gastos de capital': 'Gastos de Capital',
        'Gastos CAPITAL':    'Gastos de Capital',
        'GASTOS CAPITAL':    'Gastos de Capital',
        'Manteni- miento':   'Mantenimiento',
        'Repara- ciones':    'Reparaciones',
        'Repara-ciones':     'Reparaciones',
    }
    # Busqueda insensible a mayúsculas para variantes no listadas
    name_lower = name.lower()
    for original, corrected in REPLACEMENTS.items():
        if name_lower == original.lower():
            return corrected

    return name


def classify_column(col_num):
    """Clasifica una columna en su grupo de categoria."""
    for group, (start, end) in COL_RANGES.items():
        if start <= col_num <= end:
            return group
    return None


def get_transaction_type(group):
    """
    Determina el tipo de transaccion segun el grupo.
    'capital' = Gastos de Capital (CapEx): financiados por donaciones externas,
                NO deben afectar los porcentajes de gasto operacional mensual.
    """
    if group == 'income':
        return 'income'
    # Grupos de capital van separados del presupuesto operacional
    if group in ('gastos_k', 'cap_had'):
        return 'capital'
    return 'expense'


def get_subcategory_group(group):
    """Retorna el nombre del grupo de egresos para la referencia."""
    groups = {
        'income':   'Ingresos',
        'fondo_op': 'Fondo de Operaciones',
        'fondo_pr': 'Fondo de Propiedad',
        'gastos_k': 'Gastos Capital',
        'cap_had':  'Capital y HADIME',
        'vehiculo': 'Vehiculo',
    }
    return groups.get(group, 'Otro')


# ─────────────────────────────────────────────────────────────
# EXTRAER CATEGORIAS DEL OPER FLOW PARA IDENTIFICAR EXCLUSIONES
# ─────────────────────────────────────────────────────────────

def get_oper_flow_categories(wb):
    """
    Lee la hoja 'Oper Flow 26' y extrae las categorias que SI
    aparecen en el flujo operativo. Las categorias que NO estan
    en esta lista se deben excluir del cuadro comparativo.
    
    Tambien extrae los presupuestos mensuales.
    """
    ws = wb['Oper Flow 26 ']
    
    oper_categories = []
    budgets = {}  # {row: {mes: monto}}
    
    # Columnas de presupuesto por mes en Oper Flow:
    # K=11(Presupuesto ENE), L=12(Actual ENE), 
    # M=13(Presupuesto FEB), N=14(Actual FEB),
    # O=15(Presupuesto MAR), P=16(Actual MAR),
    # Q=17(Presupuesto ABR), R=18(Actual ABR)
    budget_cols = {1: 11, 2: 13, 3: 15, 4: 17}  # mes -> col presupuesto
    actual_cols = {1: 12, 2: 14, 3: 16, 4: 18}   # mes -> col actual
    
    for row in range(14, 33):  # Filas 14-32 son egresos en Oper Flow
        cat_name = ws.cell(row=row, column=1).value
        if cat_name and cat_name.strip():
            cat_name = cat_name.strip()
            oper_categories.append(cat_name)
            
            budgets[cat_name] = {}
            for mes, col in budget_cols.items():
                val = ws.cell(row=row, column=col).value
                budgets[cat_name][mes] = float(val) if val else 0.0
    
    # Totales de presupuesto por mes
    total_budgets = {}
    for mes, col in budget_cols.items():
        total = ws.cell(row=34, column=col).value
        total_budgets[mes] = float(total) if total else 0.0
    
    total_actuals = {}
    for mes, col in actual_cols.items():
        total = ws.cell(row=34, column=col).value
        total_actuals[mes] = float(total) if total else 0.0
    
    # Ingresos
    income_budgets = {}
    income_actuals = {}
    for mes, col in budget_cols.items():
        total = ws.cell(row=10, column=col).value
        income_budgets[mes] = float(total) if total else 0.0
    for mes, col in actual_cols.items():
        total = ws.cell(row=10, column=col).value
        income_actuals[mes] = float(total) if total else 0.0
    
    return {
        'categories': oper_categories,
        'budgets': budgets,
        'total_budgets': total_budgets,
        'total_actuals': total_actuals,
        'income_budgets': income_budgets,
        'income_actuals': income_actuals,
    }


# ─────────────────────────────────────────────────────────────
# MAPEO: Categoria del Excel mensual -> Categoria del Oper Flow
# ─────────────────────────────────────────────────────────────

CATEGORY_MAP_TO_OPER = {
    'Productos Limpieza':       'Cleaning Supplies',
    'Salario Limpieza':         'House/Dorm Cleaning Salary',
    'Manteni- miento':          'Maintenance Supplies (General)',
    'Gastos Equipo':            'EQUIPO CEM - retiros y comidas/Mainten worker Salary-repairs (hasta 2023 finales)',
    'Gastos y Honorarios Contables': 'Accountant Salary & Fees',
    'Perro':                    'Perro guardian- comida & vaccines',
    'Internet':                 'Utilities Internet',
    'Salario Voluntariado NUEVO Enero 2022': 'Salarios VOLUNTARIOS NUEVO',
    'Alberca':                  'Alberca y Irrigation (start Apr,16) buy new lona Feb',
    'Comisiones Bancarias':     'Bank Fees',
    'Relaciones Públicas':      'PR & thank you gifts',
    'Gastos Varios Voluntarios':'Misc, - gastos voluntarios',
    'Escuela Lenguaje':         'Escuela Lenguaje',
    'Propano':                  'Utilities Propane',
    'Luz':                      'Utilities Luz',
    'Basura':                   'Utilities Basura - maybe 1/mt to haul stuff away',
    'Agua & Comida':            'AGUA',
    'Impuestos Propiedad':      'Taxes (property - annual)',
    'Asoc. Civil':              'Civil Association fees & Anti money laundering notary processing fee',
    'Gasolina Van':             'Van CEM',
    'Repara- ciones Van':       'Van CEM',
    'Seguro Van':               'Van CEM',
}

# Categorias que se EXCLUYEN del Oper Flow (no son operativas)
EXCLUDED_FROM_OPER = {'gastos_k', 'cap_had'}


# ─────────────────────────────────────────────────────────────
# PROCESAMIENTO PRINCIPAL
# ─────────────────────────────────────────────────────────────

def extract_transactions(wb):
    """
    Extrae todas las transacciones de las 4 hojas mensuales.
    Retorna una lista de diccionarios con los datos de cada transaccion.
    """
    all_transactions = []
    monthly_totals = defaultdict(lambda: {'income': 0.0, 'expense': 0.0, 'capital': 0.0})
    
    for sheet_name, (month_num, month_long) in SHEETS.items():
        if sheet_name not in wb.sheetnames:
            print(f"  ADVERTENCIA: Hoja '{sheet_name}' no encontrada, saltando...")
            continue
        
        ws = wb[sheet_name]
        
        # Leer encabezados de categorias (fila 3)
        categories = {}
        for col in range(17, 56):  # Q=17 a BC=55
            cat = ws.cell(row=3, column=col).value
            if cat:
                cat = str(cat).strip()
                # Normalizar: corregir palabras partidas, mayusculas, etc.
                cat = normalize_category_name(cat)
                # Reemplazar "Van" por "Vehiculo" segun instruccion
                cat = cat.replace('Van', 'Vehiculo').replace('VAN', 'VEHICULO')
                categories[col] = cat
        
        print(f"\n  Procesando {sheet_name} ({month_long} 2026)...")
        tx_count = 0
        
        # Iterar por filas de datos
        # Patron: fila impar = fecha + datos de categorias, fila par = duplicado caja chica
        current_date = None
        
        for row in range(5, ws.max_row + 1):
            b_val = ws.cell(row=row, column=2).value
            
            # Si la celda B tiene valor, es una fila de fecha + transaccion
            if b_val is not None:
                date_str = str(b_val).strip()
                
                # Intentar parsear como fecha
                parsed = parse_date(date_str, month_num)
                if parsed:
                    current_date = parsed
                elif date_str in ('Saldo Apertura', 'Balances de la Cuenta', 'Cierre de Balance'):
                    continue
                else:
                    continue
                
                # Buscar datos en las columnas de categorias
                desc = get_description_from_row(ws, row)
                notes_col_bd = ws.cell(row=row, column=56).value  # BD = columna de notas "N-F"
                
                for col in range(17, 56):
                    val = ws.cell(row=row, column=col).value
                    if val is not None and val != 0:
                        # Validar que sea numerico
                        if isinstance(val, str):
                            val = val.strip()
                            if not val:
                                continue
                            try:
                                val = float(val)
                            except ValueError:
                                continue
                        amount = float(val)
                        group = classify_column(col)
                        if not group:
                            continue
                        
                        tx_type = get_transaction_type(group)
                        cat_name = categories.get(col, f'Col_{col}')
                        subgroup = get_subcategory_group(group)
                        
                        # Para ingresos, los valores son negativos en el Excel
                        if tx_type == 'income':
                            amount = abs(amount)
                        
                        # Acumular totales
                        if tx_type == 'capital':
                            monthly_totals[month_num]['capital'] += amount
                        elif tx_type == 'income':
                            monthly_totals[month_num]['income'] += amount
                        else:
                            monthly_totals[month_num]['expense'] += amount
                        
                        tx = {
                            'date': current_date.strftime('%d/%m/%Y'),
                            'type': tx_type,
                            'category': cat_name,
                            'amount': round(abs(amount), 2),
                            'description': desc,
                            'notes': f"[{subgroup}] {notes_col_bd or ''}".strip(),
                            'payment_method': 'transfer',
                            'reference': f"{sheet_name}-R{row}",
                            'group': group,
                            'subgroup': subgroup,
                        }
                        all_transactions.append(tx)
                        tx_count += 1
        
        print(f"    -> {tx_count} transacciones extraidas")
        totals = monthly_totals[month_num]
        print(f"    -> Ingresos: ${totals['income']:,.2f}")
        print(f"    -> Egresos Operativos: ${totals['expense']:,.2f}")
        print(f"    -> Gastos Capital/HADIME: ${totals['capital']:,.2f}")
    
    return all_transactions, monthly_totals


def generate_comparison_table(monthly_totals, oper_data):
    """
    Genera el cuadro de Presupuesto vs Actual usando los datos
    del Oper Flow 26.
    """
    month_names = {1: 'ENE', 2: 'FEB', 3: 'MAR', 4: 'ABR'}
    
    print("\n" + "=" * 70)
    print("  PRESUPUESTO vs ACTUAL (solo gastos operativos)")
    print("=" * 70)
    print(f"  {'Mes':<6} {'Presupuesto':>12} {'Actual':>12} {'Diferencia':>12} {'Estado'}")
    print(f"  {'-'*6} {'-'*12} {'-'*12} {'-'*12} {'-'*20}")
    
    total_budget = 0
    total_actual = 0
    
    for month_num in [1, 2, 3, 4]:
        budget = oper_data['total_budgets'].get(month_num, 0)
        actual = oper_data['total_actuals'].get(month_num, 0)
        diff = actual - budget
        
        total_budget += budget
        total_actual += actual
        
        if diff > 0:
            pct = (actual / budget * 100) if budget > 0 else 0
            status = f"EXCEDIDO {pct:.0f}%"
            diff_str = f"+${diff:,.0f}"
        else:
            status = "bajo presup."
            diff_str = f"-${abs(diff):,.0f}"
        
        print(f"  {month_names[month_num]:<6} ${budget:>10,.0f} ${actual:>10,.0f} {diff_str:>12} {status}")
    
    total_diff = total_actual - total_budget
    total_pct = (total_actual / total_budget * 100) if total_budget > 0 else 0
    print(f"  {'-'*6} {'-'*12} {'-'*12} {'-'*12} {'-'*20}")
    diff_sign = "+" if total_diff > 0 else "-"
    print(f"  {'TOTAL':<6} ${total_budget:>10,.0f} ${total_actual:>10,.0f} {diff_sign}${abs(total_diff):>9,.0f} {total_pct:.1f}%")
    print("=" * 70)
    
    return {
        'total_budget': total_budget,
        'total_actual': total_actual,
        'total_diff': total_diff,
        'total_pct': total_pct,
    }


def export_to_csv(transactions, filename):
    """
    Exporta las transacciones al formato CSV de importacion
    de Aura Financial.
    """
    headers = [
        'transaction_date',
        'transaction_type',
        'category',
        'amount',
        'description',
        'notes',
        'payment_method',
        'reference_number',
        'source_account_id',
        'destination_account_id',
        'related_user_id',
        'status',
    ]
    
    with open(filename, 'w', newline='', encoding='utf-8-sig') as f:
        writer = csv.DictWriter(f, fieldnames=headers)
        writer.writeheader()
        
        for tx in transactions:
            row = {
                'transaction_date': tx['date'],
                'transaction_type': tx['type'],
                'category': tx['category'],
                'amount': f"{tx['amount']:.2f}",
                'description': tx['description'],
                'notes': tx['notes'],
                'payment_method': tx['payment_method'],
                'reference_number': tx['reference'],
                'source_account_id': '',
                'destination_account_id': '',
                'related_user_id': '',
                'status': 'approved',
            }
            writer.writerow(row)
    
    return len(transactions)


# ─────────────────────────────────────────────────────────────
# MAIN
# ─────────────────────────────────────────────────────────────

def main():
    print("=" * 70)
    print("  EXTRACTOR FINANCIERO 2026 -> Aura Business Suite")
    print("=" * 70)
    
    if not os.path.exists(EXCEL_FILE):
        print(f"\n  ERROR: No se encontro el archivo '{EXCEL_FILE}'")
        return
    
    print(f"\n  Cargando: {EXCEL_FILE}")
    wb = openpyxl.load_workbook(EXCEL_FILE, data_only=True)
    
    # 1. Obtener datos del Oper Flow
    print("\n  Leyendo Oper Flow 26...")
    oper_data = get_oper_flow_categories(wb)
    print(f"    -> {len(oper_data['categories'])} categorias operativas encontradas:")
    for cat in oper_data['categories']:
        print(f"       - {cat}")
    
    # 2. Extraer transacciones
    print("\n" + "-" * 70)
    print("  EXTRAYENDO TRANSACCIONES")
    print("-" * 70)
    transactions, monthly_totals = extract_transactions(wb)
    
    # 3. Generar cuadro comparativo
    comparison = generate_comparison_table(monthly_totals, oper_data)
    
    # 4. Exportar a CSV
    print(f"\n  Exportando {len(transactions)} transacciones a '{OUTPUT_CSV}'...")
    count = export_to_csv(transactions, OUTPUT_CSV)
    print(f"  -> {count} registros escritos exitosamente")
    
    # 5. Resumen por grupo
    print("\n" + "-" * 70)
    print("  RESUMEN POR GRUPO DE CATEGORIA")
    print("-" * 70)
    group_totals = defaultdict(float)
    group_counts = defaultdict(int)
    for tx in transactions:
        group_totals[tx['subgroup']] += tx['amount']
        group_counts[tx['subgroup']] += 1
    
    for group in sorted(group_totals.keys()):
        print(f"  {group:<25} {group_counts[group]:>4} txns  ${group_totals[group]:>12,.2f}")
    
    print(f"\n  {'TOTAL':25} {len(transactions):>4} txns  ${sum(group_totals.values()):>12,.2f}")
    
    print("\n" + "=" * 70)
    print(f"  ARCHIVO GENERADO: {OUTPUT_CSV}")
    print(f"  Listo para importar en: Aura Suite > Finanzas > Importar")
    print("=" * 70)
    
    wb.close()


if __name__ == '__main__':
    main()
