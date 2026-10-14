<?php if (is_null($user)): ?>
null
<?php else: ?>
{
	"id": {{ user.id }},
	"name": {{ user.name | string }},
	"email": {{ user.email | string }}
}
<?php endif ?>
