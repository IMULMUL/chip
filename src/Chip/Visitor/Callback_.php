<?php
/**
 * Created by PhpStorm.
 * User: phithon
 * Date: 2019/1/18
 * Time: 23:46
 */

namespace Chip\Visitor;


use Chip\BaseVisitor;
use Chip\Code;
use Chip\Exception\NodeTypeException;
use Chip\Message;
use Chip\Traits\FunctionInfo;
use Chip\Traits\TypeHelper;
use Chip\Traits\Variable;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;

class Callback_ extends BaseVisitor
{
    use Variable, TypeHelper, FunctionInfo;

    protected $checkNodeClass = [
        FuncCall::class
    ];

    protected $function_with_callback = [];

    function __construct(Message $message)
    {
        parent::__construct($message);
        $this->function_with_callback = array_reduce(FUNCTION_WITH_CALLABLE, function ($carry, $item) {
            if (array_key_exists($item['function'], $carry)) {
                $carry[$item['function']][] = $item['pos'];
            } else {
                $carry[$item['function']] = [$item['pos']];
            }

            return $carry;
        }, []);
    }

    /**
     * @param FuncCall $node
     * @return bool
     */
    public function checkNode(Node $node)
    {
        return parent::checkNode($node) && $this->isMethod($node, array_keys($this->function_with_callback));
    }

    /**
     * @param FuncCall $node
     */
    public function process(Node $node)
    {
        try {
            $fname = $this->getFunctionName($node);
        } catch (NodeTypeException $e) {
            return;
        }

        foreach($this->function_with_callback[$fname] as $pos) {
            if ($pos >= 0 && array_key_exists($pos, $node->args)) {
                $arg = $node->args[$pos];
            } elseif ($pos < 0 && array_key_exists(count($node->args) + $pos, $node->args)) {
                $arg = $node->args[ count($node->args) + $pos ];
            } else {
                continue ;
            }

            if ($this->hasDynamicExpr($arg->value)) {
                $this->message->danger($node, __CLASS__, "{$fname}第{$pos}个参数包含动态变量或函数，可能有远程代码执行的隐患");
            } elseif (!$this->isClosure($arg->value)) {
                $this->message->warning($node, __CLASS__, "{$fname}第{$pos}个参数，请使用闭包函数");
            }
        }
    }
}