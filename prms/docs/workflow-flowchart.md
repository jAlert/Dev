# Policy Proposals — Workflow Flowchart

```mermaid
flowchart TD
    START([🟢 Proponent\nCreates Proposal]) --> DRAFT[Draft]
    DRAFT --> SUBMIT[Submit for Approval]
    SUBMIT --> S10

    %% ── Stage 10 ──────────────────────────────────────────────────────────────
    S10["📋 Stage 10\nInitial Document Review\n── TRC Secretariat ──"]

    S10 -->|"✅ Approve\n(docs complete,\nfirst submission)"| S20
    S10 -->|"↩ Return\n(incomplete docs)"| REV_DOCS[Proponent\nRevises Documents]
    REV_DOCS --> SUBMIT

    S10 -->|"🔀 Forward TRC\n(post-revision →\nTRC path)"| S30
    S10 -->|"🔀 Forward Ad Ref\n(post-revision →\nAd Ref path)"| S40

    %% ── Stage 20 ──────────────────────────────────────────────────────────────
    S20["🔍 Stage 20\nReviewer Review\n── Reviewer ──\n⏱ 10-day auto-advance"]

    S20 -->|"✅ Approve or\n⏰ Auto-advance\n(no comments)"| S30
    S20 -->|"↩ Return\n(has comments)"| REV_REVIEW[Proponent\nRevises Proposal]
    REV_REVIEW -->|"Resubmit → Stage 10\nTRC routes via\nForward TRC ▶"| S10

    %% ── Stage 30 ──────────────────────────────────────────────────────────────
    S30["🏛 Stage 30\nTRC Deliberation\n── TRC Secretariat ──\n📎 Upload Minutes of Meeting"]

    S30 -->|"🔀 Forward Ad Ref\n(no revision needed\n→ PTWG)"| S50
    S30 -->|"↩ Return\n(revision needed)"| REV_TRC[Proponent\nRevises Proposal]

    REV_TRC -->|"Resubmit → Stage 10\nTRC routes via\nForward TRC ▶"| TRC_ROUTE{TRC Secretariat\nRouting Decision}
    TRC_ROUTE -->|"Forward TRC\n→ Deliberate again"| S30
    TRC_ROUTE -->|"Forward Ad Ref\n→ Ad Ref review"| S40

    %% ── Stage 40 ──────────────────────────────────────────────────────────────
    S40["🔍 Stage 40\nAd Referendum Review\n── Reviewer ──\n⏱ 10-day auto-advance"]

    S40 -->|"✅ Approve or\n⏰ Auto-advance\n(no comments)"| S50
    S40 -->|"↩ Return\n(has comments)"| REV_ADREF[Proponent\nRevises Proposal]
    REV_ADREF -->|"Resubmit → Stage 10\nTRC routes via\nForward Ad Ref ▶"| S10

    %% ── Stage 50 ──────────────────────────────────────────────────────────────
    S50["🏢 Stage 50\nPTWG Endorsement\n── Office of the Director ──\n── (Receiving/Releasing) ──\n📎 Upload Endorsement Letter"]

    S50 -->|"✅ Forward\n(endorsed)"| S55
    S50 -->|"↩ Return\n(revision needed)"| REV_PTWG[Proponent\nRevises Proposal]
    REV_PTWG -->|"Resubmit → Stage 10\nTRC routes via\nForward TRC ▶"| S10

    %% ── Stage 55 ──────────────────────────────────────────────────────────────
    S55["📬 Stage 55\nEndorsed — Waiting for PTWG\n── Office of the Director ──\n── (Receiving/Releasing) ──\n📎 Upload PTWG Return Document"]

    S55 -->|"🔀 Proceed to Signing"| S60
    S55 -->|"🔀 Forward TRC\n(PTWG has comments\n→ back to deliberation)"| S30

    %% ── Stage 60 ──────────────────────────────────────────────────────────────
    S60["✍ Stage 60\nFinal — Signing & Publication\n── TRC Secretariat ──\n📎 Upload Signed Policy\n🔚 is_final = true"]

    S60 -->|"✅ Approve"| COMPLETED

    COMPLETED([🟢 COMPLETED\nPolicy Published])

    %% ── Styling ───────────────────────────────────────────────────────────────
    classDef stage        fill:#1e3a5f,color:#fff,stroke:#4a90d9,stroke-width:2px,rx:8
    classDef action       fill:#2d6a4f,color:#fff,stroke:#52b788,stroke-width:1px,rx:6
    classDef terminal     fill:#145214,color:#fff,stroke:#52b788,stroke-width:2px,rx:20
    classDef decision     fill:#5c3d00,color:#fff,stroke:#f4a261,stroke-width:2px
    classDef draft        fill:#3d3d3d,color:#fff,stroke:#aaa,stroke-width:1px,rx:6

    class S10,S20,S30,S40,S50,S55,S60 stage
    class REV_DOCS,REV_REVIEW,REV_TRC,REV_ADREF,REV_PTWG action
    class COMPLETED,START terminal
    class TRC_ROUTE decision
    class DRAFT,SUBMIT draft
```

---

## Path Summary

| Path | Steps |
|---|---|
| **Happy path** | Submit → S10 Approve → S20 Approve → S30 Forward Ad Ref → S50 Forward → S55 Proceed → S60 Approve → ✅ Completed |
| **Incomplete docs** | S10 Return → Revise → Resubmit → S10 |
| **Reviewer has comments** | S20 Return → Revise → S10 → Forward TRC → S30 |
| **TRC needs revision (TRC loop)** | S30 Return → Revise → S10 → Forward TRC → S30 |
| **TRC needs revision (Ad Ref)** | S30 Return → Revise → S10 → Forward Ad Ref → S40 → S50 |
| **PTWG has comments** | S55 Forward TRC → S30 → (continues from S30) |
| **Auto-advance (S20 / S40)** | No reviewer action in 10 working days → advances to next stage |

## Roles

| Role | Stages |
|---|---|
| **Proponent** | Creates, submits, revises |
| **TRC Secretariat** | S10, S30, S60 — reviews, routes, deliberates, signs |
| **Reviewer** | S20, S40 — provides comments, 10-day deadline |
| **Office of the Director (Receiving/Releasing)** | S50, S55 — receives PTWG endorsement, releases to signing |



# Steps on building the system stages

Step 1 — Create Roles /admin/roles

  Create four roles in order:
  1. Proponent
  2. TRC Secretariat
  3. Reviewer
  4. Receiving/Releasing

  ---
  Step 2 — Create the Module /builder/modules/create

  Fill in:
  - Name: Policy Proposals
  - Default Status: Draft
  - Buttons: ✅ Submit, ✅ Draft, ✅ Return

  Add 8 fields:

  ┌────────────────────────┬─────────────────────────────────────────────────────┬──────────┐
  │          Name          │                        Type                         │ Required │
  ├────────────────────────┼─────────────────────────────────────────────────────┼──────────┤
  │ Title                  │ Text                                                │ ✅       │
  ├────────────────────────┼─────────────────────────────────────────────────────┼──────────┤
  │ Proposal Summary       │ Textarea                                            │ ✅       │
  ├────────────────────────┼─────────────────────────────────────────────────────┼──────────┤
  │ Proponent Organization │ Text                                                │ ✅       │
  ├────────────────────────┼─────────────────────────────────────────────────────┼──────────┤
  │ Policy Type            │ Select (Policy / Guidelines / Standards / Circular) │ ✅       │
  ├────────────────────────┼─────────────────────────────────────────────────────┼──────────┤
  │ Date Prepared          │ Date                                                │ ✅       │
  ├────────────────────────┼─────────────────────────────────────────────────────┼──────────┤
  │ Proposal Document      │ Attachment                                          │ ✅       │
  ├────────────────────────┼─────────────────────────────────────────────────────┼──────────┤
  │ Minutes of Meeting     │ Attachment                                          │ —        │
  ├────────────────────────┼─────────────────────────────────────────────────────┼──────────┤
  │ Revision Notes         │ Textarea                                            │ —        │
  └────────────────────────┴─────────────────────────────────────────────────────┴──────────┘

  ▎ Saving the module auto-creates all 7 permissions (view-, create-, edit-, delete-, change-status-, review-, approve-policy_proposals) —      
  nothing manual needed.

  ---
  Step 3 — Assign Permissions to Roles /admin/roles

  Edit each role and tick the permissions:

  ┌──────────────────────┬────────────────────┐
  │         Role         │    Permissions     │
  ├──────────────────────┼────────────────────┤
  │ Proponent            │ view, create, edit │
  ├──────────────────────┼────────────────────┤
  │ TRC Secretariat      │ all 7              │
  ├──────────────────────┼────────────────────┤
  │ Reviewer             │ view, review       │
  ├──────────────────────┼────────────────────┤
  │ Receiving/Releasing  │ view, approve      │
  └──────────────────────┴────────────────────┘

  ---
  Step 4 — Create Workflow Stages /builder/workflow-stages/policy_proposals

  Create all 6 stages. Branches must be set after all stages exist (the dropdowns populate from existing stages):

  ┌───────┬───────────────────────────────┬──────────────────────┬──────────────────┬──────────┬──────────────┬────────────────┬────────────┐
  │ Order │             Name              │    Approver Role     │  Reviewer Role   │   Type   │ Auto-advance │ Default Status │ Allow Edit │
  ├───────┼───────────────────────────────┼──────────────────────┼──────────────────┼──────────┼──────────────┼────────────────┼────────────┤
  │ 10    │ Initial Document Review       │ TRC Secretariat      │ —                │ Approval │ —            │ Submitted      │ No         │
  ├───────┼───────────────────────────────┼──────────────────────┼──────────────────┼──────────┼──────────────┼────────────────┼────────────┤
  │ 20    │ Reviewer Review               │ Reviewer             │ Reviewer         │ Review   │ 10 days      │ Under Review   │ No         │
  ├───────┼───────────────────────────────┼──────────────────────┼──────────────────┼──────────┼──────────────┼────────────────┼────────────┤
  │ 30    │ TRC Deliberation              │ TRC Secretariat      │ —                │ Approval │ —            │ Under Review   │ No         │
  ├───────┼───────────────────────────────┼──────────────────────┼──────────────────┼──────────┼──────────────┼────────────────┼────────────┤
  │ 40    │ Ad Referendum Review          │ Reviewer             │ Reviewer         │ Review   │ 10 days      │ Under Review   │ No         │
  ├───────┼───────────────────────────────┼──────────────────────┼──────────────────┼──────────┼──────────────┼────────────────┼────────────┤
  │ 50    │ PTWG Endorsement              │ Receiving/Releasing  │ —                │ Review   │ —            │ Under Review   │ No         │
  ├───────┼───────────────────────────────┼──────────────────────┼──────────────────┼──────────┼──────────────┼────────────────┼────────────┤
  │ 55    │ Endorsed (Waiting for PTWG)   │ Receiving/Releasing  │ —                │ None     │ —            │ Under Review   │ No         │
  ├───────┼───────────────────────────────┼──────────────────────┼──────────────────┼──────────┼──────────────┼────────────────┼────────────┤
  │ 60    │ Final - Signing & Publication │ TRC Secretariat      │ —                │ Approval │ ✅ Final     │ Completed      │ No         │
  └───────┴───────────────────────────────┴──────────────────────┴──────────────────┴──────────┴──────────────┴────────────────┴────────────┘

  Notes on stage options:
  - **Default Status**: status automatically applied when record enters the stage. Final stage always sets Completed regardless.
  - **Allow Edit**: when disabled, the Edit button is hidden for records at this stage (even for users with edit permission). Useful for read-only review stages.
  - **Notify on Enter**: configure notifications fired automatically when a record enters the stage (e.g. email the proponent that their document is under review).
  - **Date Reminders**: schedule reminders tied to date fields in the record (e.g. remind 3 days before a deadline field).

  Then edit each stage to set branches:

  ┌──────────────────────────────┬──────────────────────────────┬───────────────────────────┐
  │            Stage             │       Branch Button(s)       │         Routes to         │
  ├──────────────────────────────┼──────────────────────────────┼───────────────────────────┤
  │ Initial Document Review      │ Forward Ad Referendum →      │ Ad Referendum Review      │
  │                              │ Forward for TRC →            │ TRC Deliberation          │
  ├──────────────────────────────┼──────────────────────────────┼───────────────────────────┤
  │ TRC Deliberation             │ For PTWG Endorsement →       │ PTWG Endorsement          │
  │                              │ Forward to Proponent (Ad Ref)│ Revise Draft Policy (S42) │
  │                              │ Forward to Proponent (Re-Del)│ Revise Draft Policy (S25) │
  ├──────────────────────────────┼──────────────────────────────┼───────────────────────────┤
  │ Endorsed (Waiting for PTWG)  │ Proceed to Signing →         │ Final — Signing           │
  │                              │ Forward TRC →                │ TRC Deliberation          │
  └──────────────────────────────┴──────────────────────────────┴───────────────────────────┘

  ---
  Step 5 — Create Users /admin/users

  Create three demo users and assign roles:

  ┌──────────────────────┬──────────────────────────┬──────────────────────┐
  │         Name         │          Email           │         Role         │
  ├──────────────────────┼──────────────────────────┼──────────────────────┤
  │ Proponent User       │ proponent@prms.local     │ Proponent            │
  ├──────────────────────┼──────────────────────────┼──────────────────────┤
  │ Secretariat User     │ secretariat@prms.local   │ TRC Secretariat      │
  ├──────────────────────┼──────────────────────────┼──────────────────────┤
  │ Reviewer User        │ reviewer@prms.local      │ Reviewer             │
  ├──────────────────────┼──────────────────────────┼──────────────────────┤
  │ Director Office User │ director@prms.local      │ Receiving/Releasing  │
  └──────────────────────┴──────────────────────────┴──────────────────────┘


