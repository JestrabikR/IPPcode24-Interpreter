<?php

namespace IPP\Student;

use RuntimeException;

class Stack {

    /** @var array<mixed> stack */
    protected array $stack;

    /**
     * @param array<mixed> $initial
     */
    public function __construct(array $initial = array()) {
        // initialize the stack
        $this->stack = $initial;
    }

    public function push(mixed $item): void {
        // prepend item to the start of the array
        array_unshift($this->stack, $item);

    }

    public function pop(): mixed {
        if (empty($this->stack)) {
            throw new RunTimeException('Stack is empty!');
        } else {
            // pop item from the start of the array
            return array_shift($this->stack);
        }
    }

    public function top() : mixed {
        if (empty($this->stack)) {
            throw new RunTimeException('Stack is empty!');
        } else {
            return current($this->stack);
        }
    }

    public function isEmpty(): bool {
        return empty($this->stack);
    }

}