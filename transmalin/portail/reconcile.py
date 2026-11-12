"""Pure conservative reconciliation; no network or accounting mutation."""
from decimal import Decimal
from collections import defaultdict

def reconcile(requests, declarations, pref_lines, invoices, payments, launch_date=None):
    output=[]
    indexes=[]
    for source in (declarations,pref_lines):
        idx=defaultdict(list)
        for r in source:
            if r.get('mission_id'): idx[r['mission_id']].append(r)
        indexes.append(idx)
    counts=defaultdict(int)
    for r in requests: counts[r['mission_id']]+=1
    for r in requests:
        mid=r['mission_id']; ds=indexes[0][mid]; ps=indexes[1][mid]
        historical=not launch_date or not r.get('date') or r['date']<launch_date
        flags=['Demande reçue'];review=[]
        if r.get('driver_id'):flags.append('Attribution connue')
        if counts[mid]>1:review.append('Identifiant mission partagé : ne pas fusionner')
        if ds:flags.append('Réalisation déclarée')
        if len(ds)>1 or len(ps)>1:review.append('Plusieurs preuves pour une mission : contrôler')
        if ps:flags.append('Présente en préfacture')
        if len(ds)==1 and r.get('driver_id') and ds[0].get('driver_id')!=r['driver_id']:review.append('Chauffeur différent')
        if len(ds)==1 and ds[0].get('date')!=r.get('date'):review.append('Date différente')
        expected=r.get('expected_ht');actual=ps[0].get('amount_ht') if len(ps)==1 else None
        if expected is not None and actual is not None and Decimal(str(expected))!=Decimal(str(actual)):review.append('Montant différent')
        # Only explicit line allocation confirms invoicing/payment for a mission.
        alloc=[i for i in invoices if mid in i.get('mission_ids',[]) and i.get('allocation_verified')]
        if alloc:flags.append('Facturée')
        paid=[p for p in payments if p.get('mission_id')==mid and p.get('bank_verified') and p.get('settles_in_full') and p.get('type')=='client_payment']
        if paid and alloc:flags.append('Réglée')
        if ds and ps and not review and len(ds)==len(ps)==1 and counts[mid]==1:flags.append('Concordance confirmée')
        else:flags.append('À rapprocher')
        if review:flags+=['Écart détecté','Vérification humaine nécessaire']
        output.append({'mission_id':mid,'historical':historical,'statuses':flags,'review':review,'missing_declaration_alert':bool(launch_date and not historical and r.get('due') and not ds and r.get('driver_id'))})
    return output
