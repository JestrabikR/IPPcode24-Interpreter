<?php

namespace IPP\Student;

use IPP\Student\Argument;

class Instruction {
    public int $order;
    public string $opcode;
    /** @var array<Argument> $arguments */
    public $arguments;

    /**
     * @param int $order
     * @param string $opcode
     * @param array<Argument> $arguments
     */
    function __construct(int $order, string $opcode, array $arguments) {
        $this->order = $order;
        $this->opcode = $opcode;
        $this->arguments = $arguments;
    }
}
