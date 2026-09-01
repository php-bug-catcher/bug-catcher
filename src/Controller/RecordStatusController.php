<?php

namespace BugCatcher\Controller;

use BugCatcher\Entity\Record;
use BugCatcher\Repository\RecordRepositoryInterface;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The "fix it" / "archive it" buttons of the detail page.
 *
 * The dashboard list drives the same change through the LogList\RecordLog live component, but its
 * clearOne() cannot be reused over HTTP: it reads the record and the current status filter from
 * live props, which only exist on a live request. So the detail page posts here instead.
 */
final class RecordStatusController extends AbstractController
{
	public function __construct(
		private readonly ManagerRegistry $registry,
	) {}

	public function changeStatus(Record $record, string $status, Request $request): Response {
		$this->denyAccessUnlessGranted('ROLE_DEVELOPER');
		if (!$this->isCsrfTokenValid('record-status-' . $record->getId(), (string)$request->request->get('_token'))) {
			throw $this->createAccessDeniedException('Invalid CSRF token.');
		}

		$repo = $this->registry->getRepository($record::class);
		if (!$repo instanceof RecordRepositoryInterface) {
			throw new LogicException(sprintf('Repository of "%s" has to implement "%s".', $record::class, RecordRepositoryInterface::class));
		}

		// the list button clears the whole group from its first occurrence on, so does this one
		$oldest = $repo->findBy(
				["hash" => $record->getHash(), "status" => $record->getStatus()],
				["date" => "ASC"],
				1
			)[0] ?? $record;

		$repo->setStatus($record, $oldest->getDate(), $status, $record->getStatus(), true);

		// the detail page posts through fetch() so it can close its own tab afterwards - answering
		// with a redirect would only make it render a page nobody is going to look at
		if ($request->isXmlHttpRequest()) {
			return new Response(status: Response::HTTP_NO_CONTENT);
		}

		return $this->redirectToRoute('bug_catcher.dashboard.detail', ["record" => $record->getId()]);
	}
}
