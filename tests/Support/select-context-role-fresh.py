"""Deterministic read-only snapshot selection; never imports/runs the classifier.
Usage: python3 tests/Support/select-context-role-fresh.py SNAPSHOT OUTPUT
OUTPUT contains null labels; review Raw and freeze labels separately before evaluation.
"""
import collections
import hashlib
import json
from pathlib import Path
import re
import sys

root = Path(__file__).resolve().parents[2]
source = Path(sys.argv[1])
output = Path(sys.argv[2])
data = json.loads(source.read_text())
old = [json.loads((root / 'tests/Fixtures/context_roles' / f).read_text())
       for f in ['batch41.json', 'independent.json']]
excluded = {int(k) for fixture in old for k in fixture['jobs']}
old_facts = {c['fact']['id'] for fixture in old for c in fixture['cases']}
jobs = {j['id']: j for j in data['jobs']}
patterns = {
    'responsibility': r'仕事内容|業務内容|担当|お任せ|使用ツール',
    'required': r'必須|応募条件|必要な経験|MUST',
    'preferred': r'歓迎|WANT|尚可|優遇',
    'collaboration': r'連携|調整|部門|他部署|インターフェース',
    'company': r'会社概要|企業概要|当社|事業内容',
    'product': r'製品|装置|システム',
    'project': r'案件例|開発例|プロジェクト例|配属例|製品群例|案件|プロジェクト',
    'unknown': r'\.\.\.|…',
}
regions = {'大阪府', '京都府', '兵庫県', '奈良県', '滋賀県', '和歌山県'}
facts = [f for f in data['facts'] if f['job_posting_id'] not in excluded
         and f['id'] not in old_facts
         and jobs[f['job_posting_id']]['occupation'] in {'機械設計', '電気設計'}
         and jobs[f['job_posting_id']]['region'] in regions
         and jobs[f['job_posting_id']]['unavailable_at'] is None]
counts = {k: collections.Counter() for k in ['occupation', 'provider_key', 'fact_key', 'region']}
seen = set()
cases = []
# Five rounds across all strata reduce the effect of the first stratum consuming jobs.
for round_index in range(5):
    for stratum, pattern in patterns.items():
        candidates = [f for f in facts if f['job_posting_id'] not in seen
                      and re.search(pattern, f['evidence_text'] or '')]
        def rank(f):
            j = jobs[f['job_posting_id']]
            return (counts['occupation'][j['occupation']], counts['provider_key'][j['provider_key']],
                    counts['fact_key'][f['fact_key']], counts['region'][j['region']], j['id'], f['fact_key'], f['id'])
        if not candidates:
            raise RuntimeError('Insufficient candidates: '+stratum)
        f = min(candidates, key=rank)
        j = jobs[f['job_posting_id']]
        seen.add(j['id'])
        for k in counts:
            counts[k][f[k] if k == 'fact_key' else j[k]] += 1
        cases.append({'fact': f, 'expected_role': None, 'label_reason': None,
                      'selection_stratum': stratum, 'selection_round': round_index + 1})
result = {
    'name': 'batch43_fresh', 'label_source': 'Unlabeled; must freeze coding-agent expected candidates before prediction; not human gold standard',
    'snapshot_sha256': hashlib.sha256(source.read_bytes()).hexdigest(),
    'classifier_sha256_before_labeling': hashlib.sha256((root / 'app/Services/ContextRoleClassifier.php').read_bytes()).hexdigest(),
    'excluded_job_ids': sorted(excluded), 'excluded_fact_ids': sorted(old_facts),
    'selection_patterns': patterns,
    'selection_protocol': '5 rounds x 8 strata; evidence regex; active 12-cell only; one Fact/job; minimum lexicographic counts occupation, provider, fact_key, region, then job ID/key/fact ID; no predictions',
    'jobs': {str(k): {field: jobs[k][field] for field in ['id', 'company_name', 'title', 'description', 'provider_key', 'occupation', 'region']} for k in sorted(seen)},
    'cases': cases,
}
output.write_text(json.dumps(result, ensure_ascii=False, indent=2)+'\n')
print(json.dumps({k: dict(v) for k, v in counts.items()}, ensure_ascii=False))
