<?php

namespace App\Enums;

enum AssessmentType: string
{
    use Concerns;

    case Exam = 'exam';
    case Quiz = 'quiz';
    case Assignment = 'assignment';
    case Project = 'project';
    case Participation = 'participation';
}
