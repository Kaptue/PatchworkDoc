HOW TO : PARSER
===============


PARSER
------

Le Parser désigne un mécanisme, implémenté dans la classe Patchwork_PHP_Parser, qui va permettre de réaliser la tokenisation 
d'un fichier, c'est-à-dire de l'analyser token par token tout en associant à
chaque token une étiquette (voir [1]: http://li.php.net/manual/fr/tokens.php
				pour les étiquettes).
Il permet via la manipulation des étiquettes liées au token d'obtenir des
informations, d'apporter des modifications précises sur un fichier à l'aide
d'un plugin qui est en fait une classe héritière de la classe
Patchwork_PHP_Parser.

PATCHWORK ET LES PARSERS
------------------------

Les parsers dans Patchwork sont utilisés par le mécanisme d'autoloading contenu dans le fichier **Autoloader.php**, en fait c'est le préprocesseur qui fait
appel aux classes via l'autoload et lance le parser, ainsi le fichier est parsé avant son inclusion, utilisation ou autre. 

Le PHP étant un langage qui se compile à chaque requête du côté serveur,
   certaines constantes natives à PHP faisant référence à des valeurs précises
   nécessites qu'à chaque compilation, PHP aille
   chercher la valeur de ses variables , or  avec le parser, les constantes sont
   remplacées par leur valeur avant la compilation, ce qui permet un gain de temps et
   une augmentation de la performance (micro-optimisation). Aussi il est nécessaire d'utiliser le Parser pour les constantes dynamiques (« magique ») avant
   compilation sinon Patchwork ne fonctionnera pas. 

   Exemple : À sa compilation par php la constante magique <code>__FILE__</code>
   est remplacée par la valeur située dans le cache qui peut être différente de la
   valeur du fichier source, or ce n'est pas ce qu'on souhaite, ainsi en utilisant le parser adéquate, <code>__FILE__</code> est remplacée par sa valeur d'après le fichier source. 

   Le parser peut également être utilisé pour la portabilité, en particulier
   lorsqu'une syntaxe a été supprimée d'une version à l'autre de php. 

   Dans patchwork on distingue deux types de plugins de parser. Les parsers
   spécifiques à Patchwork et les parsers indépendants de Patchwork mais améliorant son fonctionnement.

##A. Comment la tokenisation s'effectue-t-elle ?

   Lorsque le Parser reçoit un fichier php en paramètre, il décompose chaque groupe
   de caractères en un token qu'il identifie à une étiquette native à php, dans le
   cas où le token ne possède pas d'étiquettes dans php, le token lui-même fait
   office d'étiquette.

#####Exemple:	

   En entrée : <code>array()</code>

   Sortie    : <code>Source code   Token type
   array			T_ARRAY
   (			    (	
					)			    )</code>

   par l'exemple on voit que l'étiquette associée au token array est T_ARRAY, par
   contre le token "(" a pour étiquette lui-même.

   Le résultat de la tokenisation est stocké dans un tableau que l'on peut
   parcourir à l'aide des variables $lastType pour le token précédent et $penuType
   l'avant dernier token, et de la méthode <code>&getNextToken()</code> pour le
   token suivant. 

   Par la suite nous verrons qu'il est possible d'ajouter des étiquettes à un token
   notamment à l'aide de la méthode <code>createToken</code>.

##B. Comment parser un fichier

#####1. Créer et ouvrir un fichier **parser.php**

#####2. Inclure le fichier **Parser.php** en tapant <code>require 'le chemin du 
   fichier' , si vous vous trouvez dans le dossier Patchwork, insérer cette ligne 
   de code directement : <code>require
   './class/Patchwork/PHP/Parser.php';</code>

#####3. On récupère dans une variable le contenu du fichier que l'on va parser
   avec la fonction <code>$contenu = file_get_contents('Nom du fichier
				   cible');</code>

#####4. On instancie la classe, par défaut la syntaxe suivante suffit :
   <code> $parser = new Patchwork_PHP_Parser; </code>

#####5. On appelle la méthode <code>parse($contenu)</code>. On peut
   récupérer le résultat dans une variable afin de l'injecter dans un fichier 
   en suivant la syntaxe suivante : <code> file_put_contents('fichier cible',
				   buffer); </code>

#####6. Pour éxécuter le parser <code> php parser.php </code> dans un
   terminal. Dans le ca où
   vous avez choisi de rediriger le résultat dans un fichier, vous pouvez
   l'ouvrir avec votre éditeur de texte et constatez les modifications
   apportées.

   À ce stade, vous ne devriez voir aucunes modifications dans le fichier cible et c'est normal,
   pourtant la tokenisation a bien eu lieu juste qu'elle n'est pas visible. 

##C. Comment voir la tokenisation

   Si vous possédez le fichier de parser **Dumper.php**, sinon Aller directement au
   grand D : 

   - Revenir à l'étape 2, remplacer dans le require, le chemin du fichier
   **Parser.php** par celui du fichier **Dumper.php**

   - Sauter l'étape 3, à l'étape 4 instancier la classe, par défaut la
   syntaxe suivante <code> new Patchwork_PHP_Parser_Dumper($parser); </code>.

   - Exécuter le parser comme indiqué au 6. Cette fois-ci devrait être affiché
   à l'écran avec une mise en page similaire à celle donnée en exemple plus
   haut.

##D. Comment créer un plugin pour le parser

Dans cette partie, on va vous expliquer comment mettre au point un parser 


