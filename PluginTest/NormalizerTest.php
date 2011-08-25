<?= "Test_plugin_Normalizer\n";

class test 
{
	var $b=1;
	
	public function test1()
	{
			echo $this->b;
			echo "\n";
	}
}

$t = new test;
$t->test1();
