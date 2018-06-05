#!/usr/bin/env bash
php-cs-fixer fix ./exportupdates.php --rules=@PSR1,@PSR2,@Symfony,@DoctrineAnnotation
php-cs-fixer fix ./exportupdates.php --rules='{"array_syntax": {"syntax": "long"}, "binary_operator_spaces": {"align_double_arrow": true, "align_equals":true }}'
