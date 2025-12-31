<?php

declare(strict_types=1);

namespace FacebookLeadsFluentCRM\Services;

use FluentCrm\App\Models\Subscriber;
use RuntimeException;

final class FluentCrmService {

	private Logger $logger;

	public function __construct(Logger $logger) {
		$this->logger = $logger;
	}

	public function ensure_available(): void {
		if ( class_exists(Subscriber::class) === false ) {
			throw new RuntimeException('FluentCRM is required');
		}
	}

	public function upsert_contact(array $data, array $list_ids, array $tag_ids): Subscriber {
		$this->ensure_available();

		if ( empty($data['email']) ) {
			throw new RuntimeException('Email is required');
		}

		$email = sanitize_email((string) $data['email']);

		if ( $email === '' ) {
			throw new RuntimeException('Invalid email');
		}

		$data['email'] = $email;

		$subscriber = Subscriber::where('email', $email)->first();

		if ( $subscriber ) {
			unset($data['email']);
			$subscriber->fill($data);
			$subscriber->save();
		} else {
			$data['status'] = $data['status'] ?? 'subscribed';
			$subscriber     = Subscriber::create($data);
		}

		if ( $list_ids ) {
			$subscriber->attachLists($list_ids);
		}

		if ( $tag_ids ) {
			$subscriber->attachTags($tag_ids);
		}

		return $subscriber;
	}
}
