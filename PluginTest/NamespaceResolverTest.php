<?php
   		
namespace foo;

function bar1()
{
	echo "kikoo1\n";
}

namespace FOO;
use foo as Z;
class test 
{
	function bar() 
	{
		echo "kikoo";
	}

}

Z\bar1();
Const B = 1;
test::bar();
echo Z\B."\n";
