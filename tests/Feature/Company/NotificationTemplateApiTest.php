<?php

use App\Models\User;
use Modules\Company\Models\NotificationSetting;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

/**
 * HTTP contract tests for the notification template management API
 * (/api/v1/notification-templates), per docs/notification-template-management.md.
 *
 * Responses use the house envelope: { message, data } with HTTP 201 on success
 * (generalResponse default), 404 for missing rows, and Laravel's default 422
 * ({ message, errors }) for Form Request validation failures. Keys are snake_case.
 *
 * The routes sit behind `auth.session`, which falls back to the sanctum guard, so the
 * tests authenticate with actingAs($user, 'sanctum').
 */
function ntTemplate(array $overrides = []): NotificationSetting
{
    return NotificationSetting::create(array_merge([
        'action' => 'user_has_been_assigned_to_task',
        'template' => 'Hi <parameter1>, kamu ditugaskan ke task <b><parameter2></b>.',
        'template_html' => '<p>Hi &lt;parameter1&gt;, kamu ditugaskan ke task <strong>&lt;parameter2&gt;</strong>.</p>',
        'notification_channel' => ['database', 'email'],
    ], $overrides));
}

beforeEach(function () {
    actingAs(User::factory()->create(), 'sanctum');
});

describe('index', function () {
    it('returns a paginated envelope with totalData', function () {
        ntTemplate(['action' => 'alpha_event']);
        ntTemplate(['action' => 'beta_event']);
        ntTemplate(['action' => 'gamma_event']);

        $this->getJson('/api/v1/notification-templates')
            ->assertStatus(201)
            ->assertJsonPath('message', 'Success')
            ->assertJsonPath('data.totalData', 3)
            ->assertJsonCount(3, 'data.paginated');
    });

    it('filters by search on action/template', function () {
        ntTemplate(['action' => 'alpha_event']);
        ntTemplate(['action' => 'beta_event']);

        $this->getJson('/api/v1/notification-templates?search=alpha')
            ->assertStatus(201)
            ->assertJsonPath('data.totalData', 1)
            ->assertJsonPath('data.paginated.0.action', 'alpha_event');
    });

    it('filters by channel', function () {
        ntTemplate(['action' => 'with_telegram', 'notification_channel' => ['telegram']]);
        ntTemplate(['action' => 'email_only', 'notification_channel' => ['email']]);

        $this->getJson('/api/v1/notification-templates?channel=telegram')
            ->assertStatus(201)
            ->assertJsonPath('data.totalData', 1)
            ->assertJsonPath('data.paginated.0.action', 'with_telegram');
    });

    it('sorts by a whitelisted field', function () {
        ntTemplate(['action' => 'zeta_event']);
        ntTemplate(['action' => 'alpha_event']);

        $this->getJson('/api/v1/notification-templates?sort[0][field]=action&sort[0][order]=asc')
            ->assertStatus(201)
            ->assertJsonPath('data.paginated.0.action', 'alpha_event');
    });
});

describe('show', function () {
    it('returns a single template with decoded json columns', function () {
        $template = ntTemplate();

        $this->getJson("/api/v1/notification-templates/{$template->id}")
            ->assertStatus(201)
            ->assertJsonPath('data.id', $template->id)
            ->assertJsonPath('data.action', 'user_has_been_assigned_to_task')
            ->assertJsonPath('data.notification_channel', ['database', 'email']);
    });

    it('returns 404 when the template does not exist', function () {
        $this->getJson('/api/v1/notification-templates/999999')
            ->assertStatus(404)
            ->assertJsonPath('message', 'Notification template not found.');
    });
});

describe('store', function () {
    it('creates a template and returns it', function () {
        $payload = [
            'action' => 'task_has_been_hold_by_user',
            'template' => 'Hi <parameter1>, tugas <parameter2> di-hold.',
            'template_html' => '<p>Hi &lt;parameter1&gt;.</p>',
            'notification_channel' => ['database', 'telegram'],
        ];

        $this->postJson('/api/v1/notification-templates', $payload)
            ->assertStatus(201)
            ->assertJsonPath('message', 'Notification template created.')
            ->assertJsonPath('data.action', 'task_has_been_hold_by_user')
            ->assertJsonPath('data.notification_channel', ['database', 'telegram']);

        assertDatabaseHas('notification_settings', ['action' => 'task_has_been_hold_by_user']);
    });

    it('rejects a missing action, template and channel', function () {
        $this->postJson('/api/v1/notification-templates', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['action', 'template', 'notification_channel']);
    });

    it('rejects a duplicate action', function () {
        ntTemplate(['action' => 'already_exists']);

        $this->postJson('/api/v1/notification-templates', [
            'action' => 'already_exists',
            'template' => 'x',
            'notification_channel' => ['database'],
        ])->assertStatus(422)->assertJsonValidationErrors(['action']);
    });

    it('rejects a non-snake_case action and an unknown channel', function () {
        $this->postJson('/api/v1/notification-templates', [
            'action' => 'Not Valid Action',
            'template' => 'x',
            'notification_channel' => ['carrier_pigeon'],
        ])->assertStatus(422)->assertJsonValidationErrors(['action', 'notification_channel.0']);
    });
});

describe('update', function () {
    it('updates editable fields', function () {
        $template = ntTemplate(['template' => 'old body']);

        $this->putJson("/api/v1/notification-templates/{$template->id}", [
            'template' => 'new body',
            'notification_channel' => ['database'],
        ])
            ->assertStatus(201)
            ->assertJsonPath('message', 'Notification template updated.')
            ->assertJsonPath('data.template', 'new body')
            ->assertJsonPath('data.notification_channel', ['database']);

        assertDatabaseHas('notification_settings', ['id' => $template->id, 'template' => 'new body']);
    });

    it('keeps the action immutable even if one is sent', function () {
        $template = ntTemplate(['action' => 'original_action']);

        $this->putJson("/api/v1/notification-templates/{$template->id}", [
            'action' => 'hacked_action',
            'template' => 'body',
            'notification_channel' => ['database'],
        ])->assertStatus(201)->assertJsonPath('data.action', 'original_action');

        assertDatabaseHas('notification_settings', ['id' => $template->id, 'action' => 'original_action']);
        assertDatabaseMissing('notification_settings', ['action' => 'hacked_action']);
    });

    it('returns 404 when updating a missing template', function () {
        $this->putJson('/api/v1/notification-templates/999999', [
            'template' => 'body',
            'notification_channel' => ['database'],
        ])->assertStatus(404);
    });
});

describe('destroy', function () {
    it('deletes a template', function () {
        $template = ntTemplate();

        $this->deleteJson("/api/v1/notification-templates/{$template->id}")
            ->assertStatus(201)
            ->assertJsonPath('message', 'Notification template deleted.');

        assertDatabaseMissing('notification_settings', ['id' => $template->id]);
    });

    it('returns 404 when deleting a missing template', function () {
        $this->deleteJson('/api/v1/notification-templates/999999')->assertStatus(404);
    });
});

describe('metadata', function () {
    it('returns channels and the placeholder palette', function () {
        $this->getJson('/api/v1/notification-templates/metadata')
            ->assertStatus(201)
            ->assertJsonPath('data.channels', ['email', 'slack', 'telegram', 'database'])
            ->assertJsonCount(5, 'data.placeholders')
            ->assertJsonMissingPath('data.frequency_types')
            ->assertJsonMissingPath('data.trigger_events')
            ->assertJsonMissingPath('data.target_audiences');
    });
});

describe('preview', function () {
    it('renders plain and html with placeholders substituted', function () {
        $this->postJson('/api/v1/notification-templates/preview', [
            'template' => 'Hi <parameter1><bubble>Bye',
            'template_html' => '<p>Hi &lt;parameter1&gt;</p>',
            'data' => ['parameter1' => 'Zola'],
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.rendered', "Hi Zola\nBye")
            ->assertJsonPath('data.rendered_html', '<p>Hi Zola</p>');
    });

    it('rejects a preview without a template', function () {
        $this->postJson('/api/v1/notification-templates/preview', ['data' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['template']);
    });
});
