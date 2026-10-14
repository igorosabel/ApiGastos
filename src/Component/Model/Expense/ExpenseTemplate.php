<?php
use Osumi\OsumiFramework\App\Component\Model\User\UserComponent;
use Osumi\OsumiFramework\App\Component\Model\Category\CategoryComponent;
?>
<?php if (is_null($expense)): ?>
null
<?php else: ?>
{
	"id": {{ expense.id }},
	"idUser": {{ expense.id_user | number }},
	"user": <?php echo new UserComponent(['user' => $expense->getUser()]) ?>,
	"category": <?php echo is_null($expense->id_category) ? 'null' : new CategoryComponent(['category' => $expense->getCategory(), 'more' => false]) ?>,
	"concept": {{ expense.concept | string }},
	"amount": {{ expense.amount }},
	"inputAt": {{ expense.input_at | date }}
}
<?php endif ?>
