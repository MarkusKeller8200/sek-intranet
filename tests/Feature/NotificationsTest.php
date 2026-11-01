<?php

namespace Tests\Feature;

use App\Support\GuiPrototypes;
use App\Support\Notifications;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Benachrichtigungen (Glocke) und die Meldungen der GUI-Prototypen: zur Prüfung, zurück, freigegeben, Wünsche.
 */
class NotificationsTest extends TestCase
{
    public function test_review_flow_notifies_reviewers_and_developers(): void
    {
        $admin = $this->signInAs('admin');
        $this->post('/gui-prototypen/button/status', ['action' => 'pruefen', 'comment' => 'Fokusrahmen neu'])->assertRedirect();

        // Schulleitung (review-gui) erhält die Meldung, die Administration selbst nicht
        $lead = $this->signInAs('schulleitung');
        $this->assertSame(0, app(Notifications::class)->summary($admin->getAuthIdentifier())['unread']);
        $this->get('/start')->assertInertia(fn (Assert $page) => $page
            ->where('auth.notifications.unread', 1)
            ->where('auth.notifications.latest.0.title', 'Button v1.0.0 wartet auf Prüfung')
            ->where('auth.notifications.latest.0.body', 'Bemerkung: Fokusrahmen neu'));

        $status = $this->getJson('/benachrichtigungen/stand')->assertOk()->json();
        $id = $status['latest'][0]['id'];
        $this->get("/benachrichtigungen/{$id}")->assertRedirect('/gui-prototypen/button?reiter=versionen');
        $this->assertSame(0, app(Notifications::class)->summary($lead->getAuthIdentifier())['unread']);

        // Seite öffnet den Reiter, Schulleitung darf prüfen, aber keine Version anlegen
        $this->get('/gui-prototypen/button?reiter=versionen')->assertInertia(fn (Assert $page) => $page
            ->where('section', 'versionen')
            ->where('can', ['develop' => false, 'review' => true]));
        $this->post('/gui-prototypen/button/versionen', ['bump' => 'minor', 'changes' => 'x'])->assertForbidden();
        $this->post('/gui-prototypen/button/status', ['action' => 'zurueck', 'comment' => 'Kontrast zu schwach'])->assertRedirect();

        $this->signInAs('admin');
        $this->get('/benachrichtigungen')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('notifications')
            ->where('items.0.title', 'Button v1.0.0 zurück an Entwicklung')
            ->where('items.0.body', 'Bemerkung: Kontrast zu schwach'));
        $this->post('/benachrichtigungen/gelesen')->assertRedirect();
        $this->assertSame(0, app(Notifications::class)->summary($admin->getAuthIdentifier())['unread']);
    }

    public function test_wishes_notify_developers_and_authors(): void
    {
        $editor = $this->signInAs('redaktion');
        $this->get('/gui-prototypen/tabelle')->assertOk();
        $this->post('/gui-prototypen/tabelle/wuensche', ['text' => 'Spalten auf dem Handy ausblenden'])->assertRedirect();
        $this->post('/gui-prototypen/tabelle/status', ['action' => 'freigeben'])->assertForbidden();

        $admin = $this->signInAs('admin');
        $summary = app(Notifications::class)->summary($admin->getAuthIdentifier());
        $this->assertSame('Neuer Wunsch zu Tabellen', $summary['latest'][0]['title']);
        $this->assertSame('/gui-prototypen/tabelle?reiter=wuensche', $summary['latest'][0]['href']);

        // Version freigeben, dann neue Version mit dem Wunsch → Autorin wird informiert
        $this->post('/gui-prototypen/tabelle/status', ['action' => 'pruefen']);
        $this->post('/gui-prototypen/tabelle/status', ['action' => 'freigeben']);
        $wish = app(GuiPrototypes::class)->get('tabelle')['wishes'][0];
        $this->post('/gui-prototypen/tabelle/versionen', ['bump' => 'minor', 'changes' => 'Handy-Ansicht', 'wishes' => [$wish['id']]])->assertRedirect();

        $titles = array_column(app(Notifications::class)->forUser($editor->getAuthIdentifier()), 'title');
        $this->assertContains('Tabellen v1.1.0: dein Wunsch fliesst ein', $titles);
        $this->assertContains('Tabellen v1.0.0 freigegeben', $titles);
    }

    public function test_access_rules(): void
    {
        $this->signInAs('lehrperson');
        $this->get('/gui-prototypen')->assertForbidden();
        $this->get('/benachrichtigungen')->assertOk();
        $this->get('/benachrichtigungen/gibt-es-nicht')->assertNotFound();
    }
}
