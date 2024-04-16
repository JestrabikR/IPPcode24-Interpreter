<?php

namespace IPP\Student;

use InvalidArgumentException;
use IPP\Student\Stack;
use IPP\Student\Frame;
use IPP\Student\InstructionFactory;
use IPP\Student\ArgType;
use IPP\Student\CustomException;
use IPP\Core\AbstractInterpreter;
use IPP\Core\Exception\NotImplementedException;
use IPP\Core\Interface\OutputWriter;
use IPP\Core\ReturnCode;

enum Value {
    // represents nil value, because of that another structure is not needed
    // and nil value can be compared the same as any other type
    case NilValue;
}

class Interpreter extends AbstractInterpreter
{
    protected Frame $GF;
    protected Frame $TF;
    protected Stack $LFStack;

    public function execute(): int
    {
        $this->initialize();
        
        /** @var array<string,int> $labelDict (array<LabelName, InstructionOrder>)*/
        $labelDict = array();
        $callStack = new Stack(); // stack for jumps and returns
        $dataStack = new Stack(); // stack for stack instructions - PUSHS, POPS

        $instructions = $this->loadSortedInstructions();
        
        $currInstruction = null; // current instruction    

        // loop through all labels and add them to label table to be able to jump
        //   to higher order instructions
        for($i = 0; $i < count($instructions); $i++) {
            $currInstruction = $instructions[$i];

            if (strtoupper($currInstruction->opcode) == "LABEL") {
                $labelName = $currInstruction->arguments[0]->value;

                if (array_key_exists($labelName, $labelDict)) {
                    throw new CustomException("Label already exists", ReturnCode::SEMANTIC_ERROR);
                }

                $labelDict[$labelName] = $i;
            }
        }

        for($i = 0; $i < count($instructions); $i++) {
            $currInstruction = $instructions[$i];

            switch (strtoupper($currInstruction->opcode)) {
                case "MOVE":
                    $argVar = $currInstruction->arguments[0];

                    $argSymbVal = $currInstruction->arguments[1]->value;

                    if ($currInstruction->arguments[1]->argType === ArgType::ConstNil) {
                        $argSymbVal = Value::NilValue;
                    }

                    $this->setVarValue($argVar, $argSymbVal);

                    break;
                
                case "CREATEFRAME":
                    $this->TF = new Frame(array(), true); //initialized
                    break;

                case "PUSHFRAME":
                    if ($this->TF->isInitialized === false) {
                        throw new CustomException("TF does not exist", ReturnCode::FRAME_ACCESS_ERROR);
                    }
                    $this->LFStack->push($this->TF);
                    $this->TF = new Frame(); //uninitialized
                    break;
                
                case "POPFRAME":
                    if ($this->LFStack->isEmpty()) {
                        throw new CustomException("Frame stack is empty", ReturnCode::FRAME_ACCESS_ERROR);
                    }
                    
                    $this->TF = $this->LFStack->pop();
                    
                    break;

                case "DEFVAR":
                    $argVarVal = $currInstruction->arguments[0]->value; //e.g. GF@a

                    list($frameStr, $varName) = explode("@", $argVarVal);
                    
                    $frame = $this->getFrameByString($frameStr);

                    if (array_key_exists($varName, $frame->dict)) {
                        throw new CustomException("Variable already defined", ReturnCode::SEMANTIC_ERROR);
                    }

                    // add variable to frame
                    $frame->dict[$varName] = null; // null == uninitialized
                    break;

                case "CALL":
                    $callStack->push($i + 1);

                    $labelName = $currInstruction->arguments[0]->value;
                    
                    if (array_key_exists($labelName, $labelDict) === false) {
                        throw new CustomException("Label does not exist", ReturnCode::SEMANTIC_ERROR);
                    }

                    $i = $labelDict[$labelName];

                    break;
                
                case "RETURN":
                    if ($callStack->isEmpty()) {
                        throw new CustomException("Call stack is empty", ReturnCode::VALUE_ERROR);
                    }

                    // jump
                    $i = $callStack->pop();

                    break;
                
                case "PUSHS":
                    $symbolValue = $this->getSymbolValue($currInstruction->arguments[0]);

                    $dataStack->push($symbolValue);

                    break;
                case "POPS":
                    $argVar = $currInstruction->arguments[0]; //value: e.g. GF@a

                    if ($dataStack->isEmpty()) {
                        throw new CustomException("Frame stack is empty", ReturnCode::VALUE_ERROR);
                    }

                    $this->setVarValue($argVar, $dataStack->pop());
                
                    break;
                
                case "ADD":
                    $argVar = $currInstruction->arguments[0];
                    $symbol1 = $currInstruction->arguments[1];
                    $symbol2 = $currInstruction->arguments[2];

                    $symbol1Value = $this->getSymbolValue($symbol1);
                    $symbol2Value = $this->getSymbolValue($symbol2);

                    if (is_int($symbol1Value) === false) {
                        throw new CustomException("ADD operands must be integers", ReturnCode::OPERAND_TYPE_ERROR);
                    }
                    if (gettype($symbol1Value) !== gettype($symbol2Value)) {
                        throw new CustomException("Cannot add different types", ReturnCode::OPERAND_TYPE_ERROR);
                    }

                    $this->setVarValue($argVar, $symbol1Value + $symbol2Value);

                    break;
                
                case "SUB":
                    $argVar = $currInstruction->arguments[0];
                    $symbol1 = $currInstruction->arguments[1];
                    $symbol2 = $currInstruction->arguments[2];

                    $symbol1Value = $this->getSymbolValue($symbol1);
                    $symbol2Value = $this->getSymbolValue($symbol2);

                    if (is_int($symbol1Value) === false) {
                        throw new CustomException("SUB operands must be integers", ReturnCode::OPERAND_TYPE_ERROR);
                    }
                    if (gettype($symbol1Value) !== gettype($symbol2Value)) {
                        throw new CustomException("Cannot subtract different types", ReturnCode::OPERAND_TYPE_ERROR);
                    }

                    $this->setVarValue($argVar, $symbol1Value - $symbol2Value);

                    break;
                
                case "MUL":
                    $argVar = $currInstruction->arguments[0];
                    $symbol1 = $currInstruction->arguments[1];
                    $symbol2 = $currInstruction->arguments[2];

                    $symbol1Value = $this->getSymbolValue($symbol1);
                    $symbol2Value = $this->getSymbolValue($symbol2);

                    if (is_int($symbol1Value) === false) {
                        throw new CustomException("MUL operands must be integers", ReturnCode::OPERAND_TYPE_ERROR);
                    }
                    if (gettype($symbol1Value) !== gettype($symbol2Value)) {
                        throw new CustomException("Cannot multiply different types", ReturnCode::OPERAND_TYPE_ERROR);
                    }

                    $this->setVarValue($argVar, $symbol1Value * $symbol2Value);
                    
                    break;
                
                case "IDIV":
                    $argVar = $currInstruction->arguments[0];
                    $symbol1 = $currInstruction->arguments[1];
                    $symbol2 = $currInstruction->arguments[2];

                    $symbol1Value = $this->getSymbolValue($symbol1);
                    $symbol2Value = $this->getSymbolValue($symbol2);

                    if (is_int($symbol1Value) === false) {
                        throw new CustomException("DIV operands must be integers", ReturnCode::OPERAND_TYPE_ERROR);
                    }
                    if (gettype($symbol1Value) !== gettype($symbol2Value)) {
                        throw new CustomException("Cannot divide different types", ReturnCode::OPERAND_TYPE_ERROR);
                    }
                    if ($symbol2Value === 0) {
                        throw new CustomException("Zero division", ReturnCode::OPERAND_VALUE_ERROR);
                    }

                    $this->setVarValue($argVar, intdiv($symbol1Value, $symbol2Value));
                    
                    break;
                
                case "LT":
                    $argVar = $currInstruction->arguments[0];
                    $symbol1 = $currInstruction->arguments[1];
                    $symbol2 = $currInstruction->arguments[2];

                    $symbol1Value = $this->getSymbolValue($symbol1);
                    $symbol2Value = $this->getSymbolValue($symbol2);

                    if (($symbol1Value === Value::NilValue) || ($symbol2Value === Value::NilValue)) {
                        throw new CustomException("Cannot compare nil", ReturnCode::OPERAND_TYPE_ERROR);
                    }

                    if (gettype($symbol1Value) !== gettype($symbol2Value)) {
                        throw new CustomException("Cannot compare different types", ReturnCode::OPERAND_TYPE_ERROR);
                    }

                    $this->setVarValue($argVar, $symbol1Value < $symbol2Value);

                    break;
                
                case "GT":
                    $argVar = $currInstruction->arguments[0];
                    $symbol1 = $currInstruction->arguments[1];
                    $symbol2 = $currInstruction->arguments[2];

                    $symbol1Value = $this->getSymbolValue($symbol1);
                    $symbol2Value = $this->getSymbolValue($symbol2);

                    if (($symbol1Value === Value::NilValue) || ($symbol2Value === Value::NilValue)) {
                        throw new CustomException("Cannot compare nil", ReturnCode::OPERAND_TYPE_ERROR);
                    }

                    if (gettype($symbol1Value) !== gettype($symbol2Value)) {
                        throw new CustomException("Cannot compare different types", ReturnCode::OPERAND_TYPE_ERROR);
                    }

                    $this->setVarValue($argVar, $symbol1Value > $symbol2Value);

                    break;
                
                case "EQ":
                    $argVar = $currInstruction->arguments[0];
                    $symbol1 = $currInstruction->arguments[1];
                    $symbol2 = $currInstruction->arguments[2];

                    $symbol1Value = $this->getSymbolValue($symbol1);
                    $symbol2Value = $this->getSymbolValue($symbol2);

                    if (($symbol1Value === Value::NilValue) || ($symbol2Value === Value::NilValue)) {
                        $this->setVarValue($argVar, $symbol1Value === $symbol2Value);
                        break;
                    }

                    if (gettype($symbol1Value) !== gettype($symbol2Value)) {
                        throw new CustomException("Cannot compare different types", ReturnCode::OPERAND_TYPE_ERROR);
                    }

                    $this->setVarValue($argVar, $symbol1Value === $symbol2Value);

                    break;

                case "AND":
                    $argVar = $currInstruction->arguments[0];
                    $symbol1 = $currInstruction->arguments[1];
                    $symbol2 = $currInstruction->arguments[2];

                    $symbol1Value = $this->getSymbolValue($symbol1);
                    $symbol2Value = $this->getSymbolValue($symbol2);

                    if ((is_bool($symbol1Value) === false) || (is_bool($symbol2Value) === false)) {
                        throw new CustomException("AND expects operands of type bool", ReturnCode::OPERAND_TYPE_ERROR);
                    }

                    $this->setVarValue($argVar, $symbol1Value && $symbol2Value);

                    break;
                
                case "OR":
                    $argVar = $currInstruction->arguments[0];
                    $symbol1 = $currInstruction->arguments[1];
                    $symbol2 = $currInstruction->arguments[2];

                    $symbol1Value = $this->getSymbolValue($symbol1);
                    $symbol2Value = $this->getSymbolValue($symbol2);

                    if ((is_bool($symbol1Value) === false) || (is_bool($symbol2Value) === false)) {
                        throw new CustomException("OR expects operands of type bool", ReturnCode::OPERAND_TYPE_ERROR);
                    }

                    $this->setVarValue($argVar, $symbol1Value || $symbol2Value);

                    break;
                
                case "NOT":
                    $argVar = $currInstruction->arguments[0];
                    $symbol1 = $currInstruction->arguments[1];

                    $symbol1Value = $this->getSymbolValue($symbol1);

                    if ((is_bool($symbol1Value) === false)) {
                        throw new CustomException("NOT expects operand of type bool", ReturnCode::OPERAND_TYPE_ERROR);
                    }

                    $this->setVarValue($argVar, !$symbol1Value);

                    break;
    

                case "INT2CHAR":
                    $argVar = $currInstruction->arguments[0];
                    $symbol1 = $currInstruction->arguments[1];

                    $symbol1Value = $this->getSymbolValue($symbol1);

                    if (is_int($symbol1Value) === false) {
                        throw new CustomException("INT2CHAR expects symbol with integer value as second argument", ReturnCode::OPERAND_TYPE_ERROR);
                    }

                    if (($symbol1Value < 0) || ($symbol1Value > 255)) {
                        throw new CustomException("Wrong symbol value", ReturnCode::STRING_OPERATION_ERROR);
                    }

                    $this->setVarValue($argVar, chr($symbol1Value));

                    break;

                case "STRI2INT":
                    $argVar = $currInstruction->arguments[0];
                    $symbol1 = $currInstruction->arguments[1];
                    $symbol2 = $currInstruction->arguments[2];

                    $symbol1Value = $this->getSymbolValue($symbol1);
                    $symbol2Value = $this->getSymbolValue($symbol2);

                    if (is_string($symbol1Value) === false) {
                        throw new CustomException("SETCHAR expects second argument of type string", ReturnCode::OPERAND_TYPE_ERROR);
                    }
                    else if (is_int($symbol2Value) === false) {
                        throw new CustomException("SETCHAR expects third argument of type int", ReturnCode::OPERAND_TYPE_ERROR);
                    }

                    $len = strlen($symbol1Value);

                    if ($symbol2Value > $len - 1) {
                        throw new CustomException("Index out of range", ReturnCode::STRING_OPERATION_ERROR);
                    }

                    $this->setVarValue($argVar, ord($symbol1Value[$symbol2Value]));

                    break;
                case "READ":
                    $argVar = $currInstruction->arguments[0];
                    $argType = $currInstruction->arguments[1];

                    if ($argType->argType !== ArgType::Type) {
                        throw new CustomException("Argument is not a type", ReturnCode::INVALID_SOURCE_STRUCTURE);
                    }

                    $val = null;

                    if ($argType->value === "int") {
                        $val = $this->input->readInt();
                    }
                    else if ($argType->value === "bool") {
                        $val = $this->input->readBool();
                    }
                    else if ($argType->value === "string") {
                        $val = $this->input->readString();
                    }

                    if ($val === null) {
                        $this->setVarValue($argVar, Value::NilValue);
                        break;
                    }
                    
                    $this->setVarValue($argVar, $val);

                    break;

                case "WRITE":
                    $symbol = $currInstruction->arguments[0]; // value: e.g. GF@a, 123, Hello
                    
                    // prints varValue only if argType is Variable
                    $varValue = $this->getSymbolValue($symbol);
                    $currInstruction->arguments[0]->print($this->stdout, $varValue);

                    break;
                
                case "CONCAT":
                    $argVar = $currInstruction->arguments[0];
                    $symbol1 = $currInstruction->arguments[1];
                    $symbol2 = $currInstruction->arguments[2];

                    $symbol1Value = $this->getSymbolValue($symbol1);
                    $symbol2Value = $this->getSymbolValue($symbol2);

                    if ((is_string($symbol1Value) === false) || (is_string($symbol2Value) === false)) {
                        throw new CustomException("CONCAT expects arguments of type string", ReturnCode::OPERAND_TYPE_ERROR);
                    }

                    $this->setVarValue($argVar, $symbol1Value . $symbol2Value);

                    break;
                
                case "STRLEN":
                    $argVar = $currInstruction->arguments[0];
                    $symbol1 = $currInstruction->arguments[1];

                    $symbol1Value = $this->getSymbolValue($symbol1);

                    if ((is_string($symbol1Value) === false)) {
                        throw new CustomException("STRLEN expects arguments of type string", ReturnCode::OPERAND_TYPE_ERROR);
                    }

                    $this->setVarValue($argVar, strlen($symbol1Value));

                    break;
                
                case "GETCHAR":
                    $argVar = $currInstruction->arguments[0];
                    $symbol1 = $currInstruction->arguments[1];
                    $symbol2 = $currInstruction->arguments[2];

                    $symbol1Value = $this->getSymbolValue($symbol1);
                    $symbol2Value = $this->getSymbolValue($symbol2);

                    if (is_string($symbol1Value) === false) {
                        throw new CustomException("GETCHAR expects second argument of type string", ReturnCode::OPERAND_TYPE_ERROR);
                    }
                    else if (is_int($symbol2Value) === false) {
                        throw new CustomException("GETCHAR expects third argument of type int", ReturnCode::OPERAND_TYPE_ERROR);
                    }

                    $len = strlen($symbol1Value);

                    if ($symbol2Value > $len - 1) {
                        throw new CustomException("Index out of range", ReturnCode::STRING_OPERATION_ERROR);
                    }

                    $this->setVarValue($argVar, $symbol1Value[$symbol2Value]);

                    break;
                
                case "SETCHAR":
                    $argVar = $currInstruction->arguments[0];
                    $symbol1 = $currInstruction->arguments[1];
                    $symbol2 = $currInstruction->arguments[2];

                    $argVarValue = $this->getSymbolValue($argVar); // this method can be used because it is variable
                    $symbol1Value = $this->getSymbolValue($symbol1);
                    $symbol2Value = $this->getSymbolValue($symbol2);

                    if (is_int($symbol1Value) === false) {
                        throw new CustomException("SETCHAR expects second argument of type int", ReturnCode::OPERAND_TYPE_ERROR);
                    }
                    else if (is_string($symbol2Value) === false) {
                        throw new CustomException("SETCHAR expects third argument of type string", ReturnCode::OPERAND_TYPE_ERROR);
                    }

                    $len = strlen($argVarValue);

                    if ($symbol1Value > $len - 1) {
                        throw new CustomException("Index out of range", ReturnCode::STRING_OPERATION_ERROR);
                    }

                    if (empty($symbol2Value)) {
                        throw new CustomException("Index out of range", ReturnCode::STRING_OPERATION_ERROR);
                    }

                    $argVarValue[$symbol1Value] = $symbol2Value[0];

                    $this->setVarValue($argVar, $argVarValue);

                    break;

                case "TYPE":
                    $argVar = $currInstruction->arguments[0];
                    $symbol = $currInstruction->arguments[1];

                    $typeString = $this->getSymbolTypeAsString($symbol);
                    
                    $this->setVarValue($argVar, $typeString);

                    break;

                case "LABEL":
                    break;
            
                case "JUMP":
                    $labelName = $currInstruction->arguments[0]->value;
                    
                    if (array_key_exists($labelName, $labelDict) === false) {
                        throw new CustomException("Label does not exist", ReturnCode::SEMANTIC_ERROR);
                    }

                    $i = $labelDict[$labelName];

                    break;

                case "JUMPIFEQ":
                    $labelName = $currInstruction->arguments[0]->value;

                    if (array_key_exists($labelName, $labelDict) === false) {
                        throw new CustomException("Label does not exist", ReturnCode::SEMANTIC_ERROR);
                    }

                    $symbol1Value = $this->getSymbolValue($currInstruction->arguments[1]);

                    $symbol2Value = $this->getSymbolValue($currInstruction->arguments[2]);       

                    if (gettype($symbol1Value) !== gettype($symbol2Value)) {
                        throw new CustomException("Cannot compare different types", ReturnCode::OPERAND_TYPE_ERROR);
                    }

                    if ($symbol1Value === $symbol2Value) {
                        $i = $labelDict[$labelName];
                    }

                    break;

                case "JUMPIFNEQ":
                    $labelName = $currInstruction->arguments[0]->value;

                    if (array_key_exists($labelName, $labelDict) === false) {
                        throw new CustomException("Label does not exist", ReturnCode::SEMANTIC_ERROR);
                    }

                    $symbol1Value = $this->getSymbolValue($currInstruction->arguments[1]);

                    $symbol2Value = $this->getSymbolValue($currInstruction->arguments[2]);       

                    if (gettype($symbol1Value) !== gettype($symbol2Value)) {
                        throw new CustomException("Cannot compare different types", ReturnCode::OPERAND_TYPE_ERROR);
                    }

                    if ($symbol1Value !== $symbol2Value) {
                        $i = $labelDict[$labelName];
                    }

                    break;
                
                case "EXIT":
                    $symbolValue = $this->getSymbolValue($currInstruction->arguments[0]);

                    if ($currInstruction->arguments[0]->argType !== ArgType::ConstInt) {
                        throw new CustomException("Invalid exit code", ReturnCode::OPERAND_TYPE_ERROR);
                    }

                    if (($symbolValue < 0) || ($symbolValue > 9)) {
                        throw new CustomException("Invalid exit code", ReturnCode::OPERAND_VALUE_ERROR);
                    }

                    return $symbolValue;
                
                case "DPRINT":
                    $symbol = $currInstruction->arguments[0];
                    $symbolValue = $this->getSymbolValue($symbol);
                    
                    $symbol->print($this->stderr, $symbolValue);

                    break;

                case "BREAK":
                    $this->stderr->writeString("\n======Break======\n");
                    
                    $this->stderr->writeString("Instruction order number:" . $currInstruction->order . "\n");

                    $this->stderr->writeString("GF: \n");
                    foreach($this->GF->dict as $key => $value) {
                        $this->stderr->writeString("\t" . $key . " : " . $value . "\n");
                    }

                    $this->stderr->writeString("Completed instructions: " . $i . "\n");

                    break;
                }

        }

        return 0;
    }

    private function initialize(): void {
        $this->GF = new Frame(array(), true);
        $this->TF = new Frame(array(), false);
        $this->LFStack = new Stack();
    }

    private function getFrameByString(string $frameStr) : Frame {
        $frame = null;

        if ($frameStr == "GF") {
            $frame = $this->GF;

        } else if ($frameStr == "LF") {
            if ($this->LFStack->isEmpty()) {
                throw new CustomException("Frame stack is empty", ReturnCode::FRAME_ACCESS_ERROR);
            }

            $frame = $this->LFStack->top();

        } else if ($frameStr == "TF") {
            if ($this->TF->isInitialized === false) {
                throw new CustomException("Trying to access undefined frame", ReturnCode::FRAME_ACCESS_ERROR);
            }

            $frame = $this->TF;

        } else {
            throw new CustomException("Wrong frame type", ReturnCode::INVALID_SOURCE_STRUCTURE);
        }

        return $frame;
    }

    private function getSymbolValue(Argument $symbol) : mixed {
        
        // if $symbol->argType is Constant the value remains the same
        if (($symbol->argType === ArgType::ConstBool) ||
            ($symbol->argType === ArgType::ConstInt) ||
            ($symbol->argType === ArgType::ConstString)) {
                return $symbol->value;
        }

        if ($symbol->argType === ArgType::ConstNil) {
            return Value::NilValue;
        }

        if ($symbol->argType === ArgType::Variable) {
            list($frameStr, $varName) = explode("@", $symbol->value);
            
            $frame = $this->getFrameByString($frameStr);

            if (array_key_exists($varName, $frame->dict) === false) {
                throw new CustomException("Variable does not exist", ReturnCode::VARIABLE_ACCESS_ERROR);
            }

            return $frame->dict[$varName];
        }

        else {
            throw new CustomException("Is not a symbol", ReturnCode::INVALID_SOURCE_STRUCTURE);
        }
    }

    private function getSymbolTypeAsString(Argument $symbol): string {
        $value = $symbol->value;

        if ($symbol->argType === ArgType::Variable) {
            $value = $this->getSymbolValue($symbol);
            if ($value === null) { // uninitialized
                return "";
            }
        }
        
        if (is_int($value)) {
            return "int";
        }
        else if (is_bool($value)) {
            return "bool";
        }
        else if (is_string($value)) {
            return "string";
        }
        else if ($symbol->argType === ArgType::ConstNil) {
            return "nil";
        }
        else if ($value === Value::NilValue) {
            return "nil";
        }

        throw new CustomException("Wrong argument", ReturnCode::INVALID_SOURCE_STRUCTURE);
    }

    private function setVarValue(Argument $arg, mixed $value): void {
        list($frameStr, $varName) = explode("@", $arg->value);
                    
        $frame = $this->getFrameByString($frameStr);

        if (array_key_exists($varName, $frame->dict) === false) {
            throw new CustomException("Variable does not exist", ReturnCode::VARIABLE_ACCESS_ERROR);
        }

        $frame->dict[$varName] = $value;
    }

    /**
     * @return array<Instruction>
     */
    private function loadSortedInstructions() {
        $instructions = array();

        $dom = $this->source->getDOMDocument();
        $xml_instructions = $dom->getElementsByTagName("instruction");

        foreach($xml_instructions as $xml_instruction) {
            $instruction = InstructionFactory::createFromXml($xml_instruction);
            array_push($instructions, $instruction);
        }

        // sorts instructions by order attribute
        usort($instructions, array($this, "cmpInstrByOrder"));

        return $instructions;
    }

    /**
     * @return -1|1 (-1 if b->order is bigger than a->order, 1 if a->order is bigger than b->order)
     */
    private function cmpInstrByOrder(Instruction $a, Instruction $b): int
    {
        if (($a->order == $b->order) || ($a->order <= 0) || ($b->order < 0)) {
            throw new CustomException("Wrong order value", ReturnCode::INVALID_SOURCE_STRUCTURE);
        }

        return ($a->order < $b->order) ? -1 : 1;
    }

}
