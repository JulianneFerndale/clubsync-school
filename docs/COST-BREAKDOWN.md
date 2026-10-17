# ClubSync — Operating Cost Breakdown (800 Daily Users)

Prepared October 1, 2026. Covers the two paid cloud dependencies, **PostgreSQL** and the
**Google Gemini API**, for one school year (about 200 school days, 22 a month). Peso figures
use **₱62.6 = US$1** (October 1, 2026).

---

## Summary

| Setup | Per school year |
|---|---|
| **SCC server for PostgreSQL + Gemini paid tier** *(recommended)* | **≈ ₱2,000 – ₱5,100** |
| Supabase Pro + Gemini paid tier | ≈ ₱20,800 – ₱23,900 |

Not included: the web/app server, Firebase Authentication and the domain. Render's free plan
cannot serve 800 users (see the load test in [`loadtest/`](../loadtest/)).

---

## 1. Google Gemini API

Students never call Gemini. Only officers, advisers, the DSA and scheduled jobs do, so cost
follows **club activity across the 41 clubs**, not the 800 users.

| Feature | Triggered by | Calls (typical – heavy) | Tokens in / out per call |
|---|---|---|---|
| AI announcement draft | officer | 20 – 60 per day | 200 / 300 |
| AI activity proposal | officer | 20 – 60 per day | 400 / 375 |
| Bulletin recap | each completed activity | 15 – 30 per day | 550 / 310 |
| Semester report sections | one per activity at semester end | 1,025 – 2,050 per semester | 850 / 410 |
| Compliance notices | daily scan (not yet scheduled) | 100 – 300 per year | 150 / 375 |

Token counts are measured from the live app: each prompt was built from real data without
being sent, and output sizes come from drafts already stored in the database. Each output
includes about 125 tokens for the model's hidden "thinking", which Google bills as output.

Pricing is Gemini Flash at **$0.75 per million input tokens and $3.75 per million output
tokens through December 31, 2026, doubling to $1.50 / $7.50 from January 1, 2027**.

| | Typical | Heavy |
|---|---|---|
| Per month, Aug – Dec 2026 | ₱115 | ₱311 |
| Per month, from Jan 2027 | ₱229 | ₱623 |
| One semester-end report run (all clubs) | ₱140 – ₱280 | ₱280 – ₱560 |
| **Whole school year** | **≈ ₱2,000 (US$32)** | **≈ ₱5,100 (US$82)** |

---

## 2. PostgreSQL

**Expected growth:** about 4,300 new rows a day (mostly chat messages and their audit
records, then notifications) at ~0.6 KB each, or **~2.5 MB a day, ~0.5 GB per school year**
(up to ~1.2 GB with heavy chat). Today's database is 15.5 MB. Report photos are stored on
the app server's disk, not in the database.

| Option | Cost | Handles 800 daily users? |
|---|---|---|
| **SCC's own server** (PostgreSQL 17) | **₱0 in fees** | **Yes.** Needs about 1 GB of disk a year; the school supplies hardware, power and backups. |
| Supabase Free | ₱0 | **No.** 5 GB data transfer a month against an estimated 8 – 27 GB; the 500 MB limit is reached within about a year; no backups. |
| Supabase Pro | US$25/month ≈ **₱1,565/month, ≈ ₱18,780/year** | **Yes.** 8 GB disk, 250 GB transfer, 7-day backups. About US$5/month more if a larger server is needed. |

The school's own server is also faster: pages took 0.06 – 0.22 s with the database on the
same machine, against 1.5 – 3.7 s to Supabase in Tokyo.

---

## 3. Keeping the cost down

1. **Use the Gemini paid tier.** On the free tier Google may use what is sent to improve its
   products, and officers can type anything into an announcement draft (RA 10173). The free
   tier's daily limits would also cut off the semester-end run, sending those activities to
   "AI Unavailable — Manual Draft Required".
2. **Pin the model.** The app uses the `gemini-flash-latest` alias, which Google can move to a
   pricier model without notice. Set `GEMINI_MODEL=gemini-3.8-flash` to lock the price.
3. **Consider Flash-Lite** (about 60 – 70% cheaper) after checking the quality of its report
   sections.

---

## Assumptions

- **Measured:** prompt and output sizes, row sizes, current database size.
- **Assumed:** how often officers use the AI tools, activities per club per semester
  (25 typical, 50 heavy), chat volume (2 – 5 messages per user per day), and data transfer
  (20 – 40 pages per user per day at 25 – 40 KB of database data each).

**Sources:** [Gemini API pricing](https://ai.google.dev/gemini-api/docs/pricing) ·
[Gemini models](https://ai.google.dev/gemini-api/docs/models) ·
[Supabase pricing](https://supabase.com/pricing) ·
[USD–PHP rate, Oct 1, 2026](https://philnews.ph/2026/10/01/usd-to-php-exchange-rate-today-thursday-october-1-2026)
