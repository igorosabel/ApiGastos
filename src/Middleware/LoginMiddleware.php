<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Middleware;

use Osumi\OsumiFramework\App\Filter\LoginFilter;
use Osumi\OsumiFramework\Core\OMiddleware;

/**
 * Compatibility middleware generated automatically from LoginFilter.
 */
final class LoginMiddleware {
	/**
	 * Adapt the legacy Filter result to the middleware pipeline.
	 *
	 * @param string $phase Current middleware phase.
	 * @param array<string, mixed> $data Current middleware pipeline data.
	 *
	 * @return array<string, mixed> Middleware result.
	 *
	 * @throws \UnexpectedValueException If the legacy Filter returns invalid data.
	 */
	public static function handle(
		string $phase,
		array $data
	): array {
		if ($phase !== OMiddleware::PHASE_BEFORE) {
			return [];
		}

		$params = $data['params'] ?? [];
		$headers = $data['headers'] ?? [];

		if (
			!is_array($params) ||
			!is_array($headers)
		) {
			throw new \UnexpectedValueException(
				'Legacy Filter middleware received invalid request data.'
			);
		}

		$filter = new LoginFilter();

		$result = $filter->handle(
			$params,
			$headers
		);

		if (!is_array($result)) {
			throw new \UnexpectedValueException(
				'Legacy Filter LoginFilter must return an array.'
			);
		}

		if (
			($result['status'] ?? null) === 'ok'
		) {
			return [
				'context' => $result
			];
		}

		$redirect = $result['return']
			?? null;

		if ($redirect !== null) {
			if (
				!is_string($redirect) ||
				$redirect === ''
			) {
				throw new \UnexpectedValueException(
					"Legacy Filter LoginFilter returned an invalid redirect URL."
				);
			}

			return [
				'stop' => true,
				'status_code' => 302,
				'headers' => [
					'Location' => $redirect
				],
				'message' => ''
			];
		}

		return [
			'stop' => true,
			'status_code' => 403,
			'message' => ''
		];
	}
}
