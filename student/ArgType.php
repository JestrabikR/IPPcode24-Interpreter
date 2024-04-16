<?php

namespace IPP\Student;

enum ArgType: string {
    case Variable = "Variable";
    case Label = "Label";
    case ConstString = "ConstString";
    case ConstInt = "ConstInt";
    case ConstBool = "ConstBool";
    case ConstNil = "ConstNil";
    case Type = "Type";
}