<?php
use Osumi\OsumiFramework\App\Component\Model\Category\CategoryComponent;
?>
<?php if (is_null($category)): ?>
null
<?php else: ?>
{
	"id": {{ category.id }},
	"parent": <?php echo (is_null($category->id_parent) || $more === false) ? 'null' : new CategoryComponent(['category' => $category->getParent(), 'more' => false]) ?>,
	"name": {{ category.name | string }},
	"color": {{ category.name | string }},
	"recurring": {{ category.recurring | bool }},
	"recurringAmount": {{ category.recurring_amount | float }},
	"recurringAt": {{ category.recurring_at | date }}
}
<?php endif ?>
