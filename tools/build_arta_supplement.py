#!/usr/bin/env python3
"""Build an idempotent, agency-scoped supplemental SQL import from the two supplied workbooks.

Usage: python tools/build_arta_supplement.py schools.xlsm contracts.xlsx output.sql
No source PII or generated SQL should be committed to a public repository.
"""
import collections
import json
import re
import sys
from pathlib import Path
from decimal import Decimal, InvalidOperation, ROUND_HALF_UP

from openpyxl import load_workbook

BANKS = {
    '011':'بانک صنعت و معدن','012':'بانک ملت','013':'بانک رفاه کارگران',
    '014':'بانک مسکن','015':'بانک سپه','016':'بانک کشاورزی',
    '017':'بانک ملی ایران','018':'بانک تجارت','019':'بانک صادرات ایران',
    '021':'پست بانک ایران','022':'بانک توسعه تعاون','053':'بانک کارآفرین',
    '054':'بانک پارسیان','055':'بانک اقتصاد نوین','056':'بانک سامان',
    '057':'بانک پاسارگاد','058':'بانک سرمایه','059':'بانک سینا',
    '060':'بانک قرض‌الحسنه مهر ایران','061':'بانک شهر','062':'بانک آینده',
    '064':'بانک گردشگری','066':'بانک دی','069':'بانک ایران زمین',
    '070':'بانک قرض‌الحسنه رسالت','075':'مؤسسه اعتباری ملل',
    '078':'بانک خاورمیانه',
}

def digits(value):
    return str(value or '').translate(str.maketrans('۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩', '01234567890123456789')).strip()

def iban(value):
    text = re.sub(r'[\s\-]+', '', digits(value).upper())
    if not re.fullmatch(r'IR\d{24}', text): return None
    return text if int(text[4:] + '1827' + text[2:4]) % 97 == 1 else None

def nid(value):
    value = digits(value)
    if value.endswith('.0'): value = value[:-2]
    return value.zfill(10) if value.isdigit() and len(value) <= 10 else None

def numeric(value):
    try:
        result=Decimal(str(value or 0))
        return str(result.quantize(Decimal('.0001'), rounding=ROUND_HALF_UP)) if result.is_finite() else '0.0000'
    except (InvalidOperation, ValueError): return '0.0000'

def quote(value):
    if value is None: return 'NULL'
    return "'" + str(value).replace('\\','\\\\').replace("'", "''") + "'"

def main(school_file, contract_file, destination):
    school=load_workbook(school_file, read_only=True, data_only=True)
    contracts=load_workbook(contract_file, read_only=True, data_only=True)
    issues=[]
    driver_rows={}
    for sheet, namecol, idcol, ibancol, start in [
        (school['رانندگان 7'],1,2,7,3),
        (contracts.active,1,2,8,3),
    ]:
        for num,row in enumerate(sheet.iter_rows(min_row=start,values_only=True),start):
            if not row[namecol]: continue
            code=nid(row[idcol]); raw=str(row[ibancol] or '').strip(); bank_iban=iban(raw)
            if not code:
                issues.append(f'{sheet.title}:{num}: missing/invalid national ID');continue
            if raw and not bank_iban: issues.append(f'{sheet.title}:{num}: invalid IBAN for ID ending {code[-4:]}')
            if code not in driver_rows:driver_rows[code]={'name':str(row[namecol]).strip(),'iban':None}
            if bank_iban:
                prior=driver_rows[code]['iban']
                if prior and prior!=bank_iban: issues.append(f'{sheet.title}:{num}: conflicting IBAN for ID ending {code[-4:]}')
                elif not prior:driver_rows[code]['iban']=bank_iban
    fees=[]
    for num,row in enumerate(school['بهای خدمات اولیا5'].iter_rows(min_row=3,values_only=True),3):
        if not row[1]:continue
        code=nid(row[2]);
        if not code:issues.append(f'fees:{num}: missing/invalid national ID');continue
        fees.append(dict(nid=code,name=str(row[1]).strip(),driver=str(row[5] or '').strip(),
            school=str(row[3] or '').strip(),distance=numeric(row[7]),days=numeric(row[9]),
            base=numeric(row[10]),oversight=numeric(row[11]),municipality=numeric(row[12]),
            vat=numeric(row[13]),police=numeric(row[14]),education=numeric(row[15]),
            company=numeric(row[16]),certificate=numeric(row[17]),insurance=numeric(row[19]),
            parent_total=numeric(row[20]),statement=numeric(row[21]),guardian_iban=iban(row[22])))
    dupes=[key for key,count in collections.Counter(item['nid'] for item in fees).items() if count>1]
    if dupes:raise ValueError(f'Duplicate fee national IDs: {len(dupes)}')
    with open(destination,'w',encoding='utf-8') as output:
        output.write("-- Supplemental Arta import; execute AFTER arta-import-20261104.sql and vehicle fix.\n")
        output.write("-- Guard: agency UUID prevents applying this data to any other tenant.\n")
        output.write("SET NAMES utf8mb4;\nSET @aid=(SELECT id FROM agencies WHERE id=1 AND uuid='8506f591-e328-4729-861a-c60dd3b92ec3' AND is_active=1);\n")
        output.write("CREATE TABLE IF NOT EXISTS school_service_import_fees (agency_id BIGINT UNSIGNED NOT NULL, academic_year VARCHAR(12) NOT NULL, period VARCHAR(20) NOT NULL, student_national_code VARCHAR(20) NOT NULL, student_name VARCHAR(255), driver_name VARCHAR(255), school_name VARCHAR(255), distance_km DECIMAL(18,4), service_days DECIMAL(18,4), driver_fee DECIMAL(20,4), oversight_fee DECIMAL(20,4), municipality_fee DECIMAL(20,4), municipality_vat DECIMAL(20,4), road_police_fee DECIMAL(20,4), education_fee DECIMAL(20,4), company_fee DECIMAL(20,4), certificate_fee DECIMAL(20,4), insurance_fee DECIMAL(20,4), parent_total DECIMAL(20,4), statement_total DECIMAL(20,4), guardian_iban VARCHAR(34), PRIMARY KEY(agency_id,academic_year,period,student_national_code), INDEX idx_school_import_student(agency_id,student_national_code)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n")
        output.write("START TRANSACTION;\n")
        for code,detail in driver_rows.items():
            bank_iban=detail['iban'];bank=BANKS.get(bank_iban[4:7]) if bank_iban else None
            if bank_iban and bank:
                output.write(f"UPDATE drivers SET iban={quote(bank_iban)},bank_name={quote(bank)} WHERE agency_id=@aid AND national_code={quote(code)} AND (iban IS NULL OR iban='' OR iban={quote(bank_iban)}) AND @aid IS NOT NULL;\n")
        cols=['student_national_code','student_name','driver_name','school_name','distance_km','service_days','driver_fee','oversight_fee','municipality_fee','municipality_vat','road_police_fee','education_fee','company_fee','certificate_fee','insurance_fee','parent_total','statement_total','guardian_iban']
        for record in fees:
            vals=[quote(record['nid']),quote(record['name']),quote(record['driver']),quote(record['school'])]
            vals += [record[k] for k in ['distance','days','base','oversight','municipality','vat','police','education','company','certificate','insurance','parent_total','statement']]
            vals += [quote(record['guardian_iban'])]
            # Snapshot is authoritative workbook evidence, not a real invoice or payment.
            output.write(f"INSERT INTO school_service_import_fees(agency_id,academic_year,period,{','.join(cols)}) SELECT @aid,'1405-1406','Mehr',"+','.join(vals)+" WHERE @aid IS NOT NULL ON DUPLICATE KEY UPDATE student_name=VALUES(student_name),driver_name=VALUES(driver_name),school_name=VALUES(school_name),distance_km=VALUES(distance_km),service_days=VALUES(service_days),driver_fee=VALUES(driver_fee),oversight_fee=VALUES(oversight_fee),municipality_fee=VALUES(municipality_fee),municipality_vat=VALUES(municipality_vat),road_police_fee=VALUES(road_police_fee),education_fee=VALUES(education_fee),company_fee=VALUES(company_fee),certificate_fee=VALUES(certificate_fee),insurance_fee=VALUES(insurance_fee),parent_total=VALUES(parent_total),statement_total=VALUES(statement_total),guardian_iban=VALUES(guardian_iban);\n")
        output.write("-- Never fabricate received money or driver payouts from a calculated statement.\n")
        output.write("COMMIT;\n")
        output.write("SELECT COUNT(*) AS imported_fee_rows,COALESCE(SUM(parent_total),0) AS parent_fees,COALESCE(SUM(statement_total),0) AS statement_fees FROM school_service_import_fees WHERE agency_id=@aid AND academic_year='1405-1406' AND period='Mehr';\n")
        output.write("SELECT COUNT(*) AS matched_student_rows FROM school_service_import_fees f JOIN students s ON s.agency_id=f.agency_id AND s.national_code=f.student_national_code WHERE f.agency_id=@aid AND f.period='Mehr';\n")
        output.write("SELECT COUNT(*) AS matched_driver_ibans FROM drivers WHERE agency_id=@aid AND iban REGEXP '^IR[0-9]{24}$';\n")
    audit={'drivers':len(driver_rows),'drivers_with_valid_iban':sum(bool(d['iban']) for d in driver_rows.values()),'fee_rows':len(fees),'warnings':len(issues),'details':issues,
           'source_totals':{field:str(sum(Decimal(item[field]) for item in fees)) for field in ['base','oversight','municipality','vat','police','education','company','certificate','insurance','statement','parent_total']}}
    Path(destination).with_suffix('.audit.json').write_text(json.dumps(audit,ensure_ascii=False,indent=2),encoding='utf-8')
    print(json.dumps({k:v for k,v in audit.items() if k!='details'},ensure_ascii=False))
    return issues

if __name__=='__main__':
    if len(sys.argv)!=4:raise SystemExit('Usage: build_arta_supplement.py schools.xlsm contracts.xlsx output.sql')
    main(*sys.argv[1:])
