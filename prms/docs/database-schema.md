# PRMS Database Schema

```mermaid
erDiagram

    users {
        bigint id PK
        string name
        string email UK
        string password
        string google_id
        text two_factor_secret
        timestamp two_factor_confirmed_at
        string theme
        boolean is_active
        timestamp email_verified_at
        timestamps created_at
        timestamps updated_at
    }

    sessions {
        string id PK
        bigint user_id FK
        string ip_address
        text user_agent
        int last_activity
    }

    modules {
        bigint id PK
        string name
        string slug UK
        string description
        string default_status
        bigint source_module_id FK
        boolean my_records_only
        int sort_order
        boolean has_submit_button
        boolean has_return_button
        boolean has_draft_button
        timestamps created_at
        timestamps updated_at
    }

    module_fields {
        bigint id PK
        bigint module_id FK
        string name
        string description
        string slug
        string type
        boolean is_required
        int sort_order
        json options_json
        json visibility_conditions
        timestamps created_at
        timestamps updated_at
    }

    records {
        bigint id PK
        bigint module_id FK
        json data
        string status
        bigint current_stage_id FK
        timestamp stage_entered_at
        bigint assigned_to FK
        bigint created_by FK
        bigint updated_by FK
        timestamps created_at
        timestamps updated_at
    }

    record_comments {
        bigint id PK
        bigint record_id FK
        bigint user_id FK
        text body
        timestamps created_at
        timestamps updated_at
    }

    record_approvals {
        bigint id PK
        bigint record_id FK
        bigint stage_id FK
        bigint user_id FK
        string action
        text comment
        timestamps created_at
        timestamps updated_at
    }

    record_histories {
        bigint id PK
        bigint record_id FK
        bigint user_id FK
        string action
        json changes_json
        timestamps created_at
        timestamps updated_at
    }

    workflow_stages {
        bigint id PK
        bigint module_id FK
        string name
        smallint order
        bigint approver_role_id FK
        boolean requires_all_approvers
        boolean is_final_approval
        boolean has_return_button
        string stage_type
        smallint auto_advance_days
        bigint branch_ad_referendum_stage_id FK
        bigint branch_trc_stage_id FK
        json branches_json
        json stage_fields_json
        boolean allow_edit
        string default_status
        timestamps created_at
        timestamps updated_at
    }

    workflows {
        bigint id PK
        bigint module_id FK
        string name
        string trigger
        json conditions_json
        timestamps created_at
        timestamps updated_at
    }

    workflow_actions {
        bigint id PK
        bigint workflow_id FK
        string type
        json config_json
        timestamps created_at
        timestamps updated_at
    }

    webhooks {
        bigint id PK
        bigint module_id FK
        string name
        string url
        json events
        string secret
        boolean is_active
        timestamps created_at
        timestamps updated_at
    }

    webhook_logs {
        bigint id PK
        bigint webhook_id FK
        string event
        json payload
        int response_code
        text response_body
        boolean success
        timestamps created_at
        timestamps updated_at
    }

    permissions {
        bigint id PK
        string name
        string guard_name
        timestamps created_at
        timestamps updated_at
    }

    roles {
        bigint id PK
        string name
        string guard_name
        timestamps created_at
        timestamps updated_at
    }

    model_has_roles {
        bigint role_id FK
        string model_type
        bigint model_id FK
    }

    model_has_permissions {
        bigint permission_id FK
        string model_type
        bigint model_id FK
    }

    role_has_permissions {
        bigint permission_id FK
        bigint role_id FK
    }

    personal_access_tokens {
        bigint id PK
        string tokenable_type
        bigint tokenable_id
        string name
        string token UK
        text abilities
        timestamps created_at
        timestamps updated_at
    }

    notifications {
        uuid id PK
        string type
        string notifiable_type
        bigint notifiable_id
        text data
        timestamp read_at
        timestamps created_at
        timestamps updated_at
    }

    %% Core domain relationships
    users ||--o{ sessions : "has"
    users ||--o{ records : "creates (created_by)"
    users ||--o{ records : "updates (updated_by)"
    users ||--o{ records : "assigned to"
    users ||--o{ record_comments : "writes"
    users ||--o{ record_approvals : "makes"
    users ||--o{ record_histories : "authors"

    %% Module structure
    modules ||--o{ module_fields : "defines"
    modules ||--o{ records : "contains"
    modules ||--o{ workflow_stages : "has"
    modules ||--o{ workflows : "has"
    modules ||--o{ webhooks : "triggers"
    modules |o--o| modules : "mirrors (source_module_id)"

    %% Record lifecycle
    records ||--o{ record_comments : "has"
    records ||--o{ record_approvals : "has"
    records ||--o{ record_histories : "has"
    records }o--|| workflow_stages : "at stage (current_stage_id)"

    %% Workflow / approval
    workflow_stages }o--|| roles : "approved by role"
    workflow_stages |o--o| workflow_stages : "branch ad-referendum (legacy FK)"
    workflow_stages |o--o| workflow_stages : "branch trc (legacy FK)"
    record_approvals }o--|| workflow_stages : "for stage"

    %% Automation
    workflows ||--o{ workflow_actions : "has"

    %% Webhooks
    webhooks ||--o{ webhook_logs : "logs"

    %% Permissions (Spatie)
    roles ||--o{ role_has_permissions : "has"
    permissions ||--o{ role_has_permissions : "granted via"
    roles ||--o{ model_has_roles : "assigned via"
    permissions ||--o{ model_has_permissions : "assigned via"
```
