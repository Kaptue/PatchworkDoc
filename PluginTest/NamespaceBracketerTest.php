<?php namespace boo;

class i 
{
	function test1()
	{ 
		echo "test1\n";
	}
}

$t = new i;
$t->test1();

namespace foo; 

class j 
{
	function test2()
	{
		echo "test2\n";
	}
}

$d = new j;
$d->test2();
?>
