<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\Routes;

use Osumi\OsumiFramework\Routing\ORoute;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\App\Middleware\LoginMiddleware;
use Osumi\OsumiFramework\App\Module\Space\CreateSpace\CreateSpaceComponent;
use Osumi\OsumiFramework\App\Module\Space\GetSpaces\GetSpacesComponent;
use Osumi\OsumiFramework\App\Module\Space\CreateInvitation\CreateInvitationComponent;
use Osumi\OsumiFramework\App\Module\Space\AcceptInvitation\AcceptInvitationComponent;
use Osumi\OsumiFramework\App\Module\Space\GetInvitations\GetInvitationsComponent;
use Osumi\OsumiFramework\App\Module\Space\RevokeInvitation\RevokeInvitationComponent;
use Osumi\OsumiFramework\App\Module\Space\GetMembers\GetMembersComponent;
use Osumi\OsumiFramework\App\Module\Space\LeaveSpace\LeaveSpaceComponent;
use Osumi\OsumiFramework\App\Module\Space\RemoveMember\RemoveMemberComponent;

ORoute::prefix('/spaces', function(): void {
    ORoute::get('', GetSpacesComponent::class, [
        OMiddleware::PHASE_BEFORE => [
            LoginMiddleware::class
        ]
    ]);

    ORoute::post('', CreateSpaceComponent::class, [
        OMiddleware::PHASE_BEFORE => [
            LoginMiddleware::class
        ]
    ]);

    ORoute::post('/invitations', CreateInvitationComponent::class, [
        OMiddleware::PHASE_BEFORE => [
            LoginMiddleware::class
        ]
    ]);

    ORoute::post('/invitations/accept', AcceptInvitationComponent::class, [
        OMiddleware::PHASE_BEFORE => [
            LoginMiddleware::class
        ]
    ]);

    ORoute::get('/invitations', GetInvitationsComponent::class, [
        OMiddleware::PHASE_BEFORE => [
            LoginMiddleware::class
        ]
    ]);

    ORoute::post('/invitations/revoke', RevokeInvitationComponent::class, [
        OMiddleware::PHASE_BEFORE => [
            LoginMiddleware::class
        ]
    ]);

    ORoute::get('/members', GetMembersComponent::class, [
        OMiddleware::PHASE_BEFORE => [
            LoginMiddleware::class
        ]
    ]);

    ORoute::post('/members/leave', LeaveSpaceComponent::class, [
        OMiddleware::PHASE_BEFORE => [
            LoginMiddleware::class
        ]
    ]);

    ORoute::post('/members/remove', RemoveMemberComponent::class, [
        OMiddleware::PHASE_BEFORE => [
            LoginMiddleware::class
        ]
    ]);
});
