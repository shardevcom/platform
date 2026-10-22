<?php

declare(strict_types=1);

namespace Tests\Feature\Communications;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Shardevcom\Communications\Domain\Data\OutboundMessage;
use Shardevcom\Communications\Domain\Enums\Channel;
use Shardevcom\Communications\Domain\Enums\MessageStatus;
use Shardevcom\Communications\Domain\Enums\TemplateFormat;
use Shardevcom\Communications\Infrastructure\Transport\Providers\FcmTransport;
use Shardevcom\Communications\Infrastructure\Transport\Providers\MetaWhatsAppTransport;
use Shardevcom\Communications\Infrastructure\Transport\Providers\TwilioTransport;
use Tests\TestCase;

final class ProviderTransportTest extends TestCase
{
    public function test_twilio_sends_an_sms_with_basic_auth_and_returns_the_sid(): void
    {
        Http::fake([
            'api.twilio.com/*' => Http::response(['sid' => 'SM123'], 201),
        ]);

        $transport = new TwilioTransport(Channel::SMS, [
            'account_sid' => 'AC123',
            'auth_token' => 'secret',
            'from' => '+15550000000',
        ]);

        $result = $transport->send($this->message(Channel::SMS, '+34600000000', 'Hello'));

        $this->assertSame(MessageStatus::SENT, $result->status);
        $this->assertSame('twilio', $result->provider);
        $this->assertSame('SM123', $result->providerMessageId);

        Http::assertSent(static fn (Request $request): bool => str_contains($request->url(), '/Accounts/AC123/Messages.json')
            && $request['To'] === '+34600000000'
            && $request['From'] === '+15550000000'
            && $request['Body'] === 'Hello'
            && $request->hasHeader('Authorization'));
    }

    public function test_twilio_whatsapp_normalizes_both_addresses(): void
    {
        Http::fake([
            'api.twilio.com/*' => Http::response(['sid' => 'SM456'], 201),
        ]);

        $transport = new TwilioTransport(Channel::WHATSAPP, [
            'account_sid' => 'AC123',
            'auth_token' => 'secret',
            'whatsapp_from' => '+15550000000',
        ]);

        $transport->send($this->message(Channel::WHATSAPP, '+34600000000', 'Hola'));

        Http::assertSent(static fn (Request $request): bool => $request['To'] === 'whatsapp:+34600000000'
            && $request['From'] === 'whatsapp:+15550000000');
    }

    public function test_meta_whatsapp_sends_a_cloud_api_text_message(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]], 200),
        ]);

        $transport = new MetaWhatsAppTransport([
            'phone_number_id' => '123456',
            'token' => 'meta-token',
            'version' => 'v20.0',
        ]);

        $result = $transport->send($this->message(Channel::WHATSAPP, '+34600000000', 'Hola'));

        $this->assertSame(MessageStatus::SENT, $result->status);
        $this->assertSame('meta_whatsapp', $result->provider);
        $this->assertSame('wamid.1', $result->providerMessageId);

        Http::assertSent(static fn (Request $request): bool => str_contains($request->url(), '/v20.0/123456/messages')
            && $request['messaging_product'] === 'whatsapp'
            && $request['to'] === '+34600000000'
            && $request['text']['body'] === 'Hola'
            && $request->hasHeader('Authorization'));
    }

    public function test_fcm_sends_a_notification_with_data(): void
    {
        Http::fake([
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/demo/messages/1'], 200),
        ]);

        $transport = new FcmTransport([
            'project_id' => 'demo',
            'token' => 'oauth-token',
        ]);

        $message = new OutboundMessage(
            Channel::PUSH,
            'user',
            '42',
            'web',
            'device-token',
            TemplateFormat::TEXT,
            'Title',
            'Body',
            ['data' => ['deep_link' => '/account', 'count' => 2]],
            'user.registered',
        );

        $result = $transport->send($message);

        $this->assertSame(MessageStatus::SENT, $result->status);
        $this->assertSame('fcm', $result->provider);
        $this->assertSame('projects/demo/messages/1', $result->providerMessageId);

        Http::assertSent(static fn (Request $request): bool => str_contains($request->url(), '/projects/demo/messages:send')
            && $request['message']['token'] === 'device-token'
            && $request['message']['notification']['title'] === 'Title'
            && $request['message']['data'] === ['deep_link' => '/account', 'count' => '2']);
    }

    private function message(Channel $channel, string $address, string $body): OutboundMessage
    {
        return new OutboundMessage(
            $channel,
            'user',
            '42',
            'web',
            $address,
            TemplateFormat::TEXT,
            null,
            $body,
            [],
            'user.registered',
        );
    }
}
