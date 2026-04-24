<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\User;
use App\Models\WorkflowStage;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PolicyProposalsSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Roles ───────────────────────────────────────────────────────────
        $roleProponent    = Role::firstOrCreate(['name' => 'Proponent',       'guard_name' => 'web']);
        $roleSecretariat  = Role::firstOrCreate(['name' => 'TRC Secretariat', 'guard_name' => 'web']);
        $roleReviewer     = Role::firstOrCreate(['name' => 'Reviewer',        'guard_name' => 'web']);

        // ── 2. Module ──────────────────────────────────────────────────────────
        $module = Module::updateOrCreate(
            ['slug' => 'policy_proposals'],
            [
                'name'              => 'Policy Proposals',
                'description'       => 'General process flow for policy proposals',
                'default_status'    => 'Draft',
                'my_records_only'   => true,
                'sort_order'        => 1,
                'has_submit_button' => true,
                'has_return_button' => true,
                'has_draft_button'  => true,
            ]
        );

        // ── 3. Module Fields ───────────────────────────────────────────────────
        $fields = [
            ['name' => 'Title',                   'slug' => 'title',                  'type' => 'text',       'is_required' => true,  'sort_order' => 1, 'options_json' => null],
            ['name' => 'Proposal Summary',         'slug' => 'proposal_summary',       'type' => 'textarea',   'is_required' => true,  'sort_order' => 2, 'options_json' => null],
            ['name' => 'Proponent Organization',   'slug' => 'proponent_organization', 'type' => 'text',       'is_required' => true,  'sort_order' => 3, 'options_json' => null],
            ['name' => 'Policy Type',              'slug' => 'policy_type',            'type' => 'select',     'is_required' => true,  'sort_order' => 4, 'options_json' => ['Policy', 'Guidelines', 'Standards', 'Circular']],
            ['name' => 'Date Prepared',            'slug' => 'date_prepared',          'type' => 'date',       'is_required' => true,  'sort_order' => 5, 'options_json' => null],
            ['name' => 'Proposal Document',        'slug' => 'proposal_document',      'type' => 'attachment', 'is_required' => true,  'sort_order' => 6, 'options_json' => null],
            ['name' => 'Minutes of Meeting',       'slug' => 'minutes_of_meeting',     'type' => 'attachment', 'is_required' => false, 'sort_order' => 7, 'options_json' => null],
            ['name' => 'Revision Notes',           'slug' => 'revision_notes',         'type' => 'textarea',   'is_required' => false, 'sort_order' => 8, 'options_json' => null],
        ];

        foreach ($fields as $field) {
            $module->fields()->updateOrCreate(
                ['module_id' => $module->id, 'slug' => $field['slug']],
                $field
            );
        }

        // ── 4. Permissions ─────────────────────────────────────────────────────
        $slug = $module->slug;
        $permissionNames = [
            "view-{$slug}", "create-{$slug}", "edit-{$slug}", "delete-{$slug}",
            "change-status-{$slug}", "review-{$slug}", "approve-{$slug}",
        ];
        foreach ($permissionNames as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $roleProponent->syncPermissions(array_filter($permissionNames, fn($p) => in_array($p, [
            "view-{$slug}", "create-{$slug}", "edit-{$slug}",
        ])));
        $roleSecretariat->syncPermissions($permissionNames);
        $roleReviewer->syncPermissions(array_filter($permissionNames, fn($p) => in_array($p, [
            "view-{$slug}", "review-{$slug}",
        ])));

        // ── 5. Workflow Stages (without branches first) ────────────────────────
        $stageData = [
            [
                'name'             => 'Initial Document Review',
                'order'            => 10,
                'approver_role_id' => $roleSecretariat->id,
                'stage_type'       => 'approval',
                'is_final_approval'=> false,
                'auto_advance_days'=> null,
            ],
            [
                'name'             => 'Reviewer Review',
                'order'            => 20,
                'approver_role_id' => $roleReviewer->id,
                'stage_type'       => 'review',
                'is_final_approval'=> false,
                'auto_advance_days'=> 10,
            ],
            [
                'name'             => 'TRC Deliberation',
                'order'            => 30,
                'approver_role_id' => $roleSecretariat->id,
                'stage_type'       => 'approval',
                'is_final_approval'=> false,
                'auto_advance_days'=> null,
            ],
            [
                'name'             => 'Ad Referendum Review',
                'order'            => 40,
                'approver_role_id' => $roleReviewer->id,
                'stage_type'       => 'review',
                'is_final_approval'=> false,
                'auto_advance_days'=> 10,
            ],
            [
                'name'             => 'PTWG Endorsement',
                'order'            => 50,
                'approver_role_id' => $roleSecretariat->id,
                'stage_type'       => 'approval',
                'is_final_approval'=> false,
                'auto_advance_days'=> null,
            ],
            [
                'name'             => 'Final - Signing & Publication',
                'order'            => 60,
                'approver_role_id' => $roleSecretariat->id,
                'stage_type'       => 'approval',
                'is_final_approval'=> true,
                'auto_advance_days'=> null,
            ],
        ];

        $stages = [];
        foreach ($stageData as $data) {
            $stages[$data['name']] = WorkflowStage::updateOrCreate(
                ['module_id' => $module->id, 'name' => $data['name']],
                array_merge($data, ['module_id' => $module->id])
            );
        }

        // ── 6. Branch Configuration ────────────────────────────────────────────
        // Stage 10: Initial Document Review
        //   - Forward Ad Ref  → Ad Referendum Review (40)  [post-revision ad ref path]
        //   - Forward TRC     → TRC Deliberation (30)      [post-revision TRC path]
        $stages['Initial Document Review']->update([
            'branch_ad_referendum_stage_id' => $stages['Ad Referendum Review']->id,
            'branch_trc_stage_id'           => $stages['TRC Deliberation']->id,
        ]);

        // Stage 30: TRC Deliberation
        //   - Forward Ad Ref  → PTWG Endorsement (50)  [no revision needed, forward to PTWG]
        $stages['TRC Deliberation']->update([
            'branch_ad_referendum_stage_id' => $stages['PTWG Endorsement']->id,
            'branch_trc_stage_id'           => null,
        ]);

        // Stage 50: PTWG Endorsement
        //   - Forward TRC     → TRC Deliberation (30)  [PTWG has comments, loop back]
        $stages['PTWG Endorsement']->update([
            'branch_ad_referendum_stage_id' => null,
            'branch_trc_stage_id'           => $stages['TRC Deliberation']->id,
        ]);

        // ── 7. Demo Users ──────────────────────────────────────────────────────
        $demoUsers = [
            ['name' => 'Proponent User',      'email' => 'proponent@prms.local',   'role' => $roleProponent],
            ['name' => 'TRC Secretariat User','email' => 'secretariat@prms.local', 'role' => $roleSecretariat],
            ['name' => 'Reviewer User',        'email' => 'reviewer@prms.local',    'role' => $roleReviewer],
        ];

        foreach ($demoUsers as $u) {
            $user = User::firstOrCreate(
                ['email' => $u['email']],
                ['name' => $u['name'], 'password' => bcrypt('password'), 'email_verified_at' => now()]
            );
            $user->assignRole($u['role']);
        }

        $this->command->info('Policy Proposals module, workflow stages, roles, and demo users created.');
    }
}
