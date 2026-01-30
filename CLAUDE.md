# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Second Brain Integration (MUST DO)

**IMPORTANT:** ใช้ `project: "UGCUNTIMATE"` ทุก MCP call

### Session Flow
```
เริ่มวัน  → get_tasks(status: "in_progress") ดูงานค้าง
        → get_pending_outcomes() + get_lessons(limit: 5)
        → get_project_context() ดู tech stack, recent decisions, bugs

เริ่มงานใหม่ → semantic_search(query: "...") ค้นหาว่าเคยทำ/เจอปัญหาคล้ายกันไหม
           → create_session(goal: "...", project: "UGCUNTIMATE")

จบงาน   → end_session(outcome: "success|partial|failed", summary: "...")
        → generate_retrospective() ถ้างานสำคัญ
```

### Auto-Capture (ระหว่างทำงาน)
| เหตุการณ์ | Tool | Required Params |
|----------|------|-----------------|
| แก้ bug สำเร็จ | `capture_bug` | error_type, error_message, solution |
| ตัดสินใจเลือก lib/approach | `capture_decision` | title, context, chosen, rationale |
| Decision มีผลลัพธ์แล้ว | `record_decision_outcome` | decision_id, outcome, would_do_again |
| เขียน code 15+ บรรทัด reusable | `save_snippet` | title, language, code |
| เรียนรู้ pattern/API ใหม่ | `capture_observation` | type, title, content |

### Tasks (ติดตามงานข้าม sessions)

**เมื่อไหร่สร้าง Task:**
- User ให้ list งานหลายอัน (features, bugs) → `create_task` ทุกอัน
- งานใหญ่ทำไม่เสร็จใน session เดียว → `create_task`
- Feature request / Bug report → `create_task` พร้อม priority + feature group

**Task Lifecycle:**
```
create_task → update_task(in_progress) → update_task(done)
                    ↓
              ถ้าติด: update_task(blocked, blocked_reason)
```

**Tools:**
| Action | Tool |
|--------|------|
| สร้าง | `create_task(title, priority, feature)` |
| เริ่มทำ | `update_task(task_id, status: "in_progress")` |
| เสร็จ | `update_task(task_id, status: "done")` |
| ดู progress | `get_project_progress()` |

**Tasks + TodoWrite ใช้ร่วมกัน:**
```
Task (DB ถาวร)          TodoWrite (session เดียว)
─────────────────       ─────────────────────────
"Fix upload bug"   →    ☐ หา error log
                        ☐ Debug service
                        ☐ เพิ่ม null check
                        ☐ Test
```
Tasks = ระดับงาน, TodoWrite = ระดับขั้นตอน

## Project Overview

UGC Ultimate - AI video generation platform สร้าง UGC content จาก text prompts ด้วย AI หลายตัว แล้วรวมเป็นวิดีโอด้วย FFmpeg

See @README.md for detailed documentation.

## Development Commands

```bash
# Frontend (from /frontend)
npm run dev          # Vite dev server :3000
npm run build        # Production build

# Backend (from /backend)
composer dev         # RECOMMENDED: runs serve + queue + logs + vite concurrently
php artisan serve    # API server only :8000
php artisan queue:listen  # Queue worker only
php artisan test     # PHPUnit tests

# Docker (full stack)
docker-compose up -d
```

## Critical Rules

**IMPORTANT: API URL Pattern**
- Frontend `VITE_API_URL` ต้องไม่มี `/api` ต่อท้าย (lib/api.ts จะ append เอง)
- ถูก: `http://localhost:8000`
- ผิด: `http://localhost:8000/api`

**IMPORTANT: Queue Jobs**
- ทุก generation job ต้องรันผ่าน queue (Redis)
- ห้ามรัน synchronously เพราะ timeout

**IMPORTANT: R2 Storage**
- Upload ต้องผ่าน `R2StorageService` เท่านั้น
- ห้ามใช้ local filesystem ใน production

## Video Generation Pipeline

```
CreateProject → GenerateConceptJob → GenerateMusicJob
             → GenerateImageJob → GenerateVideoJob → ComposeVideoJob
```

ทุก step เป็น queued job, ถ้า fail ดู `job_logs` table

## Code Conventions

- Backend: Laravel conventions, Services pattern สำหรับ business logic
- Frontend: React hooks, Context สำหรับ global state (Auth, Theme)
- API: Sanctum token auth, ทุก protected route ต้องมี `auth:sanctum` middleware

## Environment Setup

Required env vars สำหรับ backend:
- `DB_*` - PostgreSQL (Neon)
- `REDIS_*` - Queue/Cache
- `R2_*` - Cloudflare R2 storage
- `FFMPEG_PATH`, `FFPROBE_PATH` - Video processing
