<?php

namespace foo;

$a = 2;

class test 
{
	function caramba()
	{
		$b = $a + 2;
		echo $b."\n";
	}

	function carambar()
	{
		$c = $b + 2;
		echo $c."\n";
	}
}

test::caramba();
test::carambar();
