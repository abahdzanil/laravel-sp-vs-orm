"""
Analisis statistik lengkap untuk hasil benchmark Fase 2 (result/*.csv).
Menghasilkan: descriptive stats, Shapiro-Wilk normality test, paired
significance test (t-test/Wilcoxon) + effect size, ringkasan tren per
volume, dan 8 line chart (median vs volume, ORM vs SP).

Output: JSON terstruktur di scripts/analysis_output/ (dibaca ulang untuk
menyusun ANALYSIS-REPORT.md) + PNG chart di result/charts/.
"""

import json
import re
from pathlib import Path

import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
import numpy as np
import pandas as pd
from scipy import stats

ROOT = Path(r"E:\work\web-workspaces\riset-sp-vs-eloquent-laravel")
RESULT_DIR = ROOT / "result"
CHARTS_DIR = RESULT_DIR / "charts"
OUT_DIR = ROOT / "scripts" / "analysis_output"
CHARTS_DIR.mkdir(exist_ok=True, parents=True)
OUT_DIR.mkdir(exist_ok=True, parents=True)

ENTITIES = ["product", "employee"]
OPERATIONS = ["create", "read", "update", "delete"]
VOLUMES = [5000, 15000, 25000]

# ============ 1. LOAD DATA ============
pattern = re.compile(r"^(orm|sp)-(product|employee)-(create|read|update|delete)-(5000|15000|25000)\.csv$")
rows = []
for f in RESULT_DIR.glob("*.csv"):
    m = pattern.match(f.name)
    if not m:
        continue
    app, entity, operation, volume = m.groups()
    df = pd.read_csv(f)
    for _, r in df.iterrows():
        rows.append({
            "app": app,
            "entity": entity,
            "operation": operation,
            "volume": int(volume),
            "run_number": int(r["run_number"]),
            "execution_time_ms": float(r["execution_time_ms"]),
        })

data = pd.DataFrame(rows)
assert len(data) == 48 * 30, f"Expected 1440 rows (48 files x 30), got {len(data)}"
print(f"Loaded {len(data)} rows from {data.groupby(['app','entity','operation','volume']).ngroups} combinations")

# ============ 2. DESCRIPTIVE STATS (48 combos) ============
desc_rows = []
for (app, entity, operation, volume), group in data.groupby(["app", "entity", "operation", "volume"]):
    s = group["execution_time_ms"]
    q1, q3 = s.quantile(0.25), s.quantile(0.75)
    desc_rows.append({
        "app": app, "entity": entity, "operation": operation, "volume": int(volume),
        "mean": s.mean(), "median": s.median(), "std": s.std(ddof=1),
        "min": s.min(), "max": s.max(), "q1": q1, "q3": q3, "iqr": q3 - q1,
    })
desc = pd.DataFrame(desc_rows)
assert len(desc) == 48

# ============ 3. SHAPIRO-WILK NORMALITY (48 combos) ============
normality_rows = []
for (app, entity, operation, volume), group in data.groupby(["app", "entity", "operation", "volume"]):
    stat, p = stats.shapiro(group["execution_time_ms"])
    normality_rows.append({
        "app": app, "entity": entity, "operation": operation, "volume": int(volume),
        "shapiro_stat": stat, "shapiro_p": p, "normal": bool(p > 0.05),
    })
normality = pd.DataFrame(normality_rows)
assert len(normality) == 48

# ============ 4. SIGNIFICANCE TEST PER PAIR (24 pairs) ============
pair_rows = []
for entity in ENTITIES:
    for operation in OPERATIONS:
        for volume in VOLUMES:
            orm_vals = (
                data[(data.app == "orm") & (data.entity == entity) & (data.operation == operation) & (data.volume == volume)]
                .sort_values("run_number")["execution_time_ms"].to_numpy()
            )
            sp_vals = (
                data[(data.app == "sp") & (data.entity == entity) & (data.operation == operation) & (data.volume == volume)]
                .sort_values("run_number")["execution_time_ms"].to_numpy()
            )
            assert len(orm_vals) == 30 and len(sp_vals) == 30

            orm_normal = bool(normality[(normality.app == "orm") & (normality.entity == entity) & (normality.operation == operation) & (normality.volume == volume)]["normal"].iloc[0])
            sp_normal = bool(normality[(normality.app == "sp") & (normality.entity == entity) & (normality.operation == operation) & (normality.volume == volume)]["normal"].iloc[0])

            diff = orm_vals - sp_vals  # positive => ORM slower (SP faster)

            if orm_normal and sp_normal:
                test_name = "paired t-test"
                tstat, pval = stats.ttest_rel(orm_vals, sp_vals)
                effect_label = "Cohen's d (dz)"
                effect_size = float(diff.mean() / diff.std(ddof=1))
                stat_value = float(tstat)
            else:
                test_name = "Wilcoxon signed-rank"
                nonzero = diff[diff != 0]
                try:
                    wstat, pval = stats.wilcoxon(orm_vals, sp_vals)
                    wstat = float(wstat)
                except ValueError:
                    wstat, pval = float("nan"), float("nan")
                if len(nonzero) > 0:
                    ranks = stats.rankdata(np.abs(nonzero))
                    w_pos = ranks[nonzero > 0].sum()
                    w_neg = ranks[nonzero < 0].sum()
                    r_rb = float((w_pos - w_neg) / (w_pos + w_neg)) if (w_pos + w_neg) > 0 else float("nan")
                else:
                    r_rb = float("nan")
                effect_label = "rank-biserial r"
                effect_size = r_rb
                stat_value = wstat

            orm_median = float(np.median(orm_vals))
            sp_median = float(np.median(sp_vals))

            pair_rows.append({
                "entity": entity, "operation": operation, "volume": int(volume),
                "test": test_name,
                "statistic": stat_value,
                "p_value": float(pval) if pval == pval else None,  # NaN check
                "significant": bool(pval < 0.05) if pval == pval else False,
                "effect_label": effect_label,
                "effect_size": effect_size,
                "orm_mean": float(orm_vals.mean()), "sp_mean": float(sp_vals.mean()),
                "orm_median": orm_median, "sp_median": sp_median,
                "faster": "SP" if sp_median < orm_median else "ORM",
                "gap_median_ms": orm_median - sp_median,
                "orm_normal": orm_normal, "sp_normal": sp_normal,
            })

pairs = pd.DataFrame(pair_rows)
assert len(pairs) == 24

# ============ 5. TREND SUMMARY (8 combos) ============
trend_rows = []
for entity in ENTITIES:
    for operation in OPERATIONS:
        sub = pairs[(pairs.entity == entity) & (pairs.operation == operation)].sort_values("volume")
        gaps = sub["gap_median_ms"].tolist()
        medians_orm = sub["orm_median"].tolist()
        medians_sp = sub["sp_median"].tolist()

        if gaps[0] == 0:
            ratio = float("inf") if gaps[2] != 0 else 1.0
        else:
            ratio = gaps[2] / gaps[0] if gaps[0] != 0 else float("inf")

        if abs(gaps[2]) > abs(gaps[0]) * 1.15:
            trend = "melebar"
        elif abs(gaps[2]) < abs(gaps[0]) * 0.85:
            trend = "menyempit"
        else:
            trend = "relatif konsisten"

        trend_rows.append({
            "entity": entity, "operation": operation,
            "orm_median_5000": medians_orm[0], "sp_median_5000": medians_sp[0], "gap_5000": gaps[0],
            "orm_median_15000": medians_orm[1], "sp_median_15000": medians_sp[1], "gap_15000": gaps[1],
            "orm_median_25000": medians_orm[2], "sp_median_25000": medians_sp[2], "gap_25000": gaps[2],
            "trend": trend,
        })
trends = pd.DataFrame(trend_rows)
assert len(trends) == 8

# ============ 6. CHARTS (8 line charts) ============
chart_files = []
for entity in ENTITIES:
    for operation in OPERATIONS:
        sub = pairs[(pairs.entity == entity) & (pairs.operation == operation)].sort_values("volume")
        plt.figure(figsize=(6, 4))
        plt.plot(sub["volume"], sub["orm_median"], marker="o", label="ORM", color="#4C72B0")
        plt.plot(sub["volume"], sub["sp_median"], marker="s", label="SP", color="#DD8452")
        plt.xlabel("Volume data (baris)")
        plt.ylabel("Median execution time (ms)")
        plt.title(f"{entity.capitalize()} \u2014 {operation}")
        plt.xticks(VOLUMES)
        plt.legend()
        plt.grid(True, alpha=0.3)
        plt.tight_layout()
        fname = f"{entity}-{operation}.png"
        plt.savefig(CHARTS_DIR / fname, dpi=120)
        plt.close()
        chart_files.append(fname)

# ============ EXPORT ============
desc.to_json(OUT_DIR / "descriptive.json", orient="records")
normality.to_json(OUT_DIR / "normality.json", orient="records")
pairs.to_json(OUT_DIR / "pairs.json", orient="records")
trends.to_json(OUT_DIR / "trends.json", orient="records")

with open(OUT_DIR / "summary.json", "w") as f:
    json.dump({
        "total_rows": len(data),
        "total_combinations": 48,
        "normal_count": int(normality["normal"].sum()),
        "non_normal_count": int((~normality["normal"]).sum()),
        "significant_pairs": int(pairs["significant"].sum()),
        "non_significant_pairs": int((~pairs["significant"]).sum()),
        "chart_files": chart_files,
        "ttest_used": int((pairs["test"] == "paired t-test").sum()),
        "wilcoxon_used": int((pairs["test"] == "Wilcoxon signed-rank").sum()),
    }, f, indent=2)

pd.set_option("display.max_rows", None)
pd.set_option("display.width", 200)
pd.set_option("display.float_format", lambda x: f"{x:.6f}")

print("\n" + "=" * 100)
print("UJI NORMALITAS (SHAPIRO-WILK) - 48 KOMBINASI")
print("=" * 100)
print(normality.sort_values(["app", "entity", "operation", "volume"]).to_string(index=False))

print("\n" + "=" * 100)
print("UJI SIGNIFIKANSI (WILCOXON SIGNED-RANK) - 24 PASANGAN")
print("=" * 100)
print(pairs.sort_values(["entity", "operation", "volume"])[[
    "entity", "operation", "volume", "test", "p_value", "significant",
    "effect_label", "effect_size", "orm_median", "sp_median", "faster",
]].to_string(index=False))

print("\n=== SUMMARY ===")
print(f"Normal (Shapiro p>0.05): {int(normality['normal'].sum())}/48")
print(f"Non-normal: {int((~normality['normal']).sum())}/48")
print(f"Paired t-test used: {int((pairs['test']=='paired t-test').sum())}/24")
print(f"Wilcoxon used: {int((pairs['test']=='Wilcoxon signed-rank').sum())}/24")
print(f"Significant (p<0.05): {int(pairs['significant'].sum())}/24")
print(f"Charts saved: {len(chart_files)} files in {CHARTS_DIR}")
print("\nDone. JSON outputs written to", OUT_DIR)
