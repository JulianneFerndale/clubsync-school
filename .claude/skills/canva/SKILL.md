---
name: canva
description: Create pictures and designs with Canva for ClubSync — generated images, club banners and logos, event posters, flyers, social posts and landing-page photos — and save them into the project. Use whenever the user asks to make, generate, edit or restyle a picture, image, illustration, icon, poster, banner, flyer, logo or social media graphic, or mentions Canva.
---

# Canva for ClubSync

Canva is connected as an MCP server (`mcp__claude_ai_Canva__*`). Its tools are deferred: load the ones you need with one ToolSearch call before the first use, e.g.

```
ToolSearch("select:mcp__claude_ai_Canva__generate-image,mcp__claude_ai_Canva__get-generate-image-job,mcp__claude_ai_Canva__get-export-formats,mcp__claude_ai_Canva__export-design")
```

If no `mcp__claude_ai_Canva__*` tools exist in the session, tell the user to connect Canva (claude.ai → Settings → Connectors, or `/mcp`) rather than faking an image another way.

## Pick the tool by what the user asked for

| User asks for | Tool | Then |
|---|---|---|
| An image, picture, photo, illustration, icon, sticker, graphic | `generate-image` | `get-generate-image-job` until SUCCESS |
| An editable layout: poster, flyer, invitation, Instagram/Facebook post, story, infographic, presentation | `create-design` | `get-create-design-async-job` until done |
| A design in their brand kit / brand template | `list-brand-kits` → ask which → `generate-design` with `brand_kit_id` | — |
| Edit or restyle an existing picture | upload it (below) → `generate-image` with `imageReferences` | — |
| Cut out a subject | `remove-background` | — |
| A different size of an existing design | `resize-design` | — |
| Find an earlier Canva design | `search-designs` | — |

The wording decides, not the content: "an image of a poster" is `generate-image`; "a poster" is `create-design`.

**Polling.** When no widget renders (the CLI), poll the job tool yourself. For `create-design`, wait the `polling_policy.wait_seconds` it returns between calls. Don't start a second job just because the first is still pending.

**Proofread every generated design before sharing it.** `create-design` paints the layout as an image and then OCRs the text back into editable layers, so prices, URLs and words come out garbled ("$30" for $39, "Laravel Forget", "force.laravel.com"), and text blocks overlap. After the job completes, `read-design` with `open_transaction: true`, compare every number and name against the brief, fix the errors with `edit-design` (`replace_text` / `find_and_replace_text`, `position_element`, `resize_element`), and check the returned thumbnail. Committing needs the user's OK, so show them the list of fixes first. For text-heavy designs (prices, steps), using `create-design` and then correcting the text is safer than `generate-image`, whose text you can't edit.

**Always give the user the link.** After a successful `generate-image`, include the returned link as `[Open generated image](...)`. After `create-design`, share the design link (and the Canva Print link if `design.print.is_printable` is true).

## Getting the file into the project

1. Get the design ID (it starts with `D`) from the job result.
2. `get-export-formats` with that ID. Export only a format it lists.
3. `export-design` as `png` (logos, anything with transparency) or `jpg` (photos and banners, `quality` around 85). Set `width`/`height` to the target sizes below. If Canva answers "Not allowed to access design", retry at a smaller width: 1500 px failed on a 374 px-wide infographic where 1125 px (3×) worked.
4. Download the returned URL with the Bash tool. Binary files must go through `curl`; never pipe them through PowerShell `Set-Content` or `Out-File`:
   ```bash
   curl -sSL -o "public/images/<name>.jpg" "<download-url>"
   file "public/images/<name>.jpg"   # confirm it's really an image at the expected size
   ```
5. Before overwriting an existing image, check what's there (`file`, dimensions) and confirm with the user.

### Where ClubSync images go

| Purpose | Location / how it's set | Target |
|---|---|---|
| Landing-page slideshow (`start.blade.php`) | `public/images/event1.jpg` … `event4.jpg` | ~2048×1536 (4:3) JPG |
| App logo, error pages, auth, PWA icon | `public/images/clubsync_logo.png` | 1920×1080 PNG, transparent background |
| Club logo | DSA → Clubs → Edit, `logo` field → `storage/app/public/clubs/logos` | square PNG/JPG, **≤ 2 MB** |
| Club banner | DSA → Clubs → Edit, `banner` field → `storage/app/public/clubs/banners` | wide (shown at `h-40`, `object-cover`), e.g. 1600×400 JPG, **≤ 4 MB** |
| Report letterheads | `public/images/reports/*` | **don't replace** — these are the official banners and seals |

Club logos and banners belong to the database, not the repo. Save the export to the scratchpad and let the user upload it through the DSA edit form. That form handles storage, deletes the old file and writes the `club.updated` audit entry. Don't write into `storage/` directly.

## ClubSync look

- Primary green `#07873e`. Sidebar gray `#333a45`. Header near-black `#111318`. Font: Inter.
- School: Saint Columban College (SCC), Pagadian City.
- For a design that must show the real SCC crest or ClubSync logo, upload the actual file. `public/images/scc-crest.png` and `public/images/clubsync_logo.png` both work. Don't ask the AI to draw the crest; it gets it wrong.

### Uploading a local file to Canva

1. `create-upload-url` returns a single-use URL.
2. POST the raw bytes:
   ```bash
   curl -sS -X POST -H "Content-Type: application/octet-stream" --data-binary "@public/images/scc-crest.png" "<upload-url>"
   ```
3. Use the returned media ID as `{"type":"MEDIA","id":"<id>"}` in `imageReferences`, or as an asset ID. Each file needs its own upload URL, and a used or expired URL needs a fresh one.

## Rules from POLICY.md (`workflows/POLICY.md`)

- **Never generate report or activity photos.** Narrative-report photos, minutes, receipts, programs and property photos are records the officers attach. Per POLICY they are "compiled from the records, never generated". Don't produce a Canva image meant to stand in for any of them, or place one into a report.
- **AI output needs human review before it is published.** A generated image is a draft until the user approves it. Never wire Canva output into code that posts announcements, notifications or email automatically.
- **No real people.** Don't generate or edit an image to look like an identifiable SCC student, adviser or official. Don't upload students' photos or documents (enrollment certificates, school IDs, report photos) to Canva. They are student data under RA 10173, and Canva is an outside service.
- Uploading to Canva sends the file to a third party. Uploading the public crest or logo is fine. For anything else, ask the user first.

## Canva product questions

For "how do I … in Canva" questions, call `mcp__claude_ai_Canva__help` and relay its answer as written. Don't answer from memory.
