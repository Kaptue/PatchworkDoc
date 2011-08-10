HOW TO : PLUGIN FOR PARSER
==========================

INTRODUCTION
------------

Le but de ce tutoriel est en plus de faire une présentation générale du parser
de Patchwork, de donner les outils nécessaires à la réalisation d'un plugin
simple pour le parser. Des connaissances en PHP objet sont requises.

PARSER
------

Le Parser désigne un mécanisme, implémenté dans la classe Patchwork_PHP_Parser
du fichier **Parser.php**, qui va permettre de réaliser la tokenization
d'un fichier, c'est-à-dire de l'analyser token (un token peut être un caractère
un groupe de caractère) par token tout en leur associant une étiquette (voir
http://li.php.net/manual/fr/tokens.php pour les étiquettes).
Il permet via la manipulation des étiquettes liées au token d'obtenir des
informations, d'apporter des modifications précises sur un fichier à l'aide
de plugins qui sont en fait des classes héritières à celle du Parser.


PATCHWORK ET LE PARSER
----------------------

Le Parser dans Patchwork est utilisé par le mécanisme d'autoloading contenu
dans le fichier **Autoloader.php**, en fait c'est le préprocesseur qui fait
appel aux classes via l'autoload et lance le parser et tous les plugins
associés, ainsi le fichier est parsé avant son inclusion, utilisation ou
autre.

Le PHP étant un langage qui se compile à chaque requête du côté serveur,
certaines constantes natives à PHP faisant référence à des valeurs précises
nécessites qu'à chaque compilation, PHP aille chercher la valeur de ses
variables, or avec le parser, les constantes sont remplacées par leur
valeur avant la compilation, ce qui permet un gain de temps et une augmentation
de la performance (micro-optimisation). Aussi il est nécessaire d'utiliser
le Parser pour les constantes dynamiques (« magiques ») avant compilation
sinon Patchwork ne fonctionnera pas.

**Exemple** : À sa compilation par php la constante magique
<code>__FILE__</code>
est remplacée par la valeur située dans le cache qui peut être différente de la
valeur du fichier source, or ce n'est pas ce qu'on souhaite, ainsi en utilisant
le parser adéquate, <code>__FILE__</code> est remplacée par sa valeur d'après
le fichier source.

Le parser peut également être utilisé pour la portabilité, en particulier
lorsqu'une syntaxe a été supprimée d'une version à l'autre de php (voir
Problème présenté ci-dessous pour plus de détails).

En fait le fichier **Parser.php** dans lequel est contenu le mécanisme du
parser ne fait pas grand chose seul, il sert plutôt d'environnement de base
pour les plugins qui vont venir se greffer à lui et utiliser des outils
prédéfinis.

##A. Comment la tokenization s'effectue-t-elle ?

Lorsque le Parser reçoit un fichier php en paramètre, il analyse le flux de
caractères contenus dans le fichier et le décompose en groupe de caractères
qui définissent un token qu'il identifie ensuite à une étiquette native à php,
dans le cas où le token ne possède pas d'étiquettes dans php, le token lui-même
fait office d'étiquette.

**Exemple** : array();

	Sortie    | Source code | Token type
   	----------|-------------|------------
   			  |	 array		|	T_ARRAY
   			  |	 (			|   (	
			  |	 )			|   )
  
par l'exemple on voit que l'étiquette associée au token array est T_ARRAY, par
contre le token "(" a pour étiquette lui-même.

Le résultat de la tokenization est stocké dans un tableau virtuel que l'on peut
parcourir à l'aide des variables <code>$lastType</code> pour le token précédent
et <code>$penuType</code> l'avant dernier token, et de la méthode
<code>&getNextToken();</code> pour le token suivant.

Par la suite nous verrons qu'il est possible d'ajouter des étiquettes à un
token notamment à l'aide de la méthode <code>createToken();</code>.

##B. Comment "parser" un fichier

**1.** Aller à la racine du dossier Patchwork et ouvrir un fichier **parser.php**

**2.** Inclure le fichier **Parser.php** en tapant la syntaxe suivante : <code>
require './class/Patchwork/PHP/Parser.php';</code>

**3.** On récupère dans un buffer le contenu du fichier que l'on va
parser avec la fonction <code>$buffer = file_get_contents('fichier_A_Parser');
</code>.

**4.** On instancie les objets de la classe Patchwork_PHP_Parser, par défaut
la syntaxe suivante suffit : <code> $parser = new Patchwork_PHP_Parser; </code>.

**5.** On appelle la méthode de la classe que l'on vient d'instancier
<code>$parser->parse($buffer);</code> qui va parser le flux
de caractères contenus dans la variable $buffer. On peut récupérer le
résultat de la méthode parse($buffer) dans un nouveau buffer
<code>$buffer2=$parser->parse($buffer);</code>, afin de l'injecter dans un
fichier cible en suivant la syntaxe suivante : <code>file_put_contents('fichier
_cible', $buffer2); </code>.
On sauvegarde le tout.

**6.** Pour éxécuter le parser <code> php parser.php </code> dans un terminal.
Dans le cas où vous avez choisi de rediriger le résultat dans un fichier, vous
pouvez l'ouvrir avec votre éditeur de texte et constatez les modifications
apportées.

À ce stade, vous ne devriez voir aucunes modifications dans le fichier_cible
et c'est normal, pourtant la tokenization a bien eu lieu juste qu'elle n'est
pas visible.

##C. Comment voir la tokenization

Si vous ne possédez pas le fichier de parser **Dumper.php** allez
directement au grand D :

- Revenir à l'étape 2, ajouter dans le require du fichier **Dumper.php**

- Sauter l'étape 3, à l'étape 4 instancier les objets de la classe à la suite
des autres instanciations, avec la syntaxe suivante
<code> new Patchwork_PHP_Parser_Dumper($parser); </code>.

- Exécuter le parser comme indiqué au 6. Cette fois-ci devrait être affiché
à l'écran avec une mise en page similaire à celle donnée en exemple plus
haut.

##D. Comment créer un plugin pour le parser

Le but ce cette partie est de vous permettre de créer votre propre plugin, en
effet comme il a été dit plus haut, le parser est un mécanisme auquel il faut
lui rajouter des fonctions lui disant quoi faire. Le plugin représente ces
fonctions.

Comme partout en programmation, avant de se lancer dans le codage, il y a une
étape de conceptualisation du problème à passer. Cette étape étant propre à
chaque problème et personne, elle peut prendre un certain temps. Nous allons
donc procéder à la résolution d'un problème que vous suivrez étape par étape.

### Le Problème :

Dans php 5.4 de nouvelles syntaxe seront implétementées. Un tableau pourra se
déclarer de cette manière : <code>$a=[1,4,5];</code> et une fonction retournant
un tableau pourra directement être utilsée comme une variable comme ceci
<code>renvoi_tableau()[1];</code>. Or cette syntaxe n'est pas valide pour les 
anciennes versions. On va donc créer un plugin qui devra implémenter cette 
syntaxe augmentant ainsi la portabilité de Patchwork.

L'exercice consiste donc à écrire un plugin qui va transformer la syntaxe
<code>$a=[1,3,4];</code> en <code>$a=array(1,2,3);</code>.

**A Bien cibler le problème**

Il est question de remplacer le token "[" par les tokens "array" et "(" puis les
tokens "]" par des ")". Donc une première étape consistera à identifier lors de
la tokenization les tokens "[" et "]", et la deuxième étape, remplacer la
syntaxe crochet par la syntaxe array().

Vous vous êtes sûrement posés la question, mais comment peut-on être sûr qu'un
crochet fait référence à un tableau et pas à autre chose ?

Et vous avez raison, c'est une question importante puisque la réponse à cette
interrogation va déterminer l'algorithme à implémenter. La première idée que
l'on peut avoir, c'est qu'il suffit de faire une liste exhaustive des situations
dans lesquelles on est sûr que seul un tableau peut-être déclaré et d'en exclure
toutes les autres. Et donc il suffirait de regarder le token précédent et dans
certains cas, l'avant dernier pour être fixé.

Cette méthode pourrait fonctionner, mais elle est trop coûteuse en temps car
elle nécessite de connaître toutes les situations dans lesquelles un tableau
peut-être déclaré sans déclencher d'erreurs. En effet il est plus simple et plus
rapide de penser dans l'autre sens. Notre préoccupation n'est pas d'obtenir un
code juste en sortie, cela relève de la responsabilité du codeur, mais juste
de permettre l'intégration d'une syntaxe. Aussi plutôt que de s'intéresser au
cas où il faudrait un tableau, il est plus pertinent de s'intéresser au cas où
l'utilisation du crochet ne constitue pas une Parse error, déjà parce que le
nombre de possibilités d'utilisations est très faible par rapport à celui du
tableau et aussi parce qu'en réfléchissant ainsi nous sommes sûrs d'avoir la
liste exhaustive des cas d'utilisation d'un tableau et tous les autres cas
constitueraient des Fatal error qui relèverent du codeur.

Et par une série de test sur tous les tokens connus (voir lien première partie)
on a déterminé que les seuls tokens pouvant dans certains cas précéder le token 
"[" sont "]", "T_VARIABLE" et "}". 

"]" lorsqu'on souhaite accéder à une valeur dans un tableau à deux dimensions ou
plus. Exemple d'utilisation <code>$a[1][1];</code>.

"}" lorsqu'on souhaite accéder à une valeur dans un tableau.
Exemple d'utilisation <code>${"a"}[0];</code> ce qui équivaut à
<code>$a[0];</code>

**B Les outils de l'implémentation**

Maintenant que nous avons déblayé et saisi le problème, nous allons pouvoir
implémenter l'algorithme mais avant cela il est nécessaire que certaines
fonctionnalités incluses dans le fichier **Parser.php** vous soient expliquées.

<code>$callbacks;</code> : La variable callbaks est un array particulier,
c'est elle qui va contenir les méthodes et les étiquettes associées aux tokens.
Cette variable fonctionne comme un marqueur de token, c'est à dire que le plugin 
dit au parser, je veux tous les tokens ayant comme étiquette "[" et "]" et à chaque 
token reçu il applique la méthode associée.

**exemple de syntaxe** : <code>$callbaks = array( 'tagOpenBracket' => '[' );</code>.

'tagOpenBracket', désigne la méthode et '[', le token sur lequel la méthode doit
s'appliquer.

<code>$lastType;</code> : Permet d'accéder au prédécent token.

<code>$unshiftTokens();</code> : Sûrement la méthode la plus importante pour
nous car c'est elle qui va insérer de nouveaux tokens à la position suivante au
moment de son appel.

**exemple de syntaxe** : <code>$unshiftTokens(array(T_ARRAY, 'array'), '(');

le nombre d'arguments désignent le nombre de tokens à insérer, on remarque dans
l'exemple que le premier argument est un array et pas le second. En fait comme
il a été précisé dans la présentation du parser, certains tokens n'ont pas
d'étiquette, le token faisant office d'étiquette, par contre tous les tokens
possédant une étiquette que l'on souhaite insérer doivent être associés à l'aide
d'un array à une étiquette, par défaut celle native à php (voir
http://li.php.net/manual/fr/tokens.php).

Si vous l'utilisez de cette manière <code>return $unshiftTokens((array(T_ARRAY,
'array'));</code> alors avant d'insérer le token 'array',
<code>$unshiftTokens</code> supprimera le token présent à la position où elle
est appelée.

Donc pour nous il s'agira d'utiliser <code>$unshiftTokens();</code> dans des
méthodes déclenchées par les tokens "[" et "]".

Dernière chose, notre plugin devra être capable de gérer plusieurs niveaux
d'imbrications de tableaux sans se perdre, il ne devra jamais remplacer plus de
crochets fermant que de crochets ouvrants et comme on l'a vu, il existe des
cas où la syntaxe crochet n'est pas génératrice d'erreur qu'il faudra être
capable de gérer.

exemple de syntaxe pouvant poser problème : <code>[[[$a[1]]]]</code>.

Nous avons une imbrication d'array à l'intérieur de laquelle se trouve une
variable dont les crochets associés ne doivent pas subir de modifications.

Comment procéder à cela ? L'idée va être d'introduire une variable qui sera un
tableau, il devra contenir un booléen, "true" si le crochet ouvrant est remplacé
ou "false" dans le cas contraire. Et pour savoir si un crochet fermant doit être
remplacé il suffira de regarder la valeur du dernier élément du tableau.

On appelle cette variable <code>$stack = array()</code>.

À ce stade vous possédez donc tous les outils modulo les notions de php pour créer
le plugin.

**C Le Code**

On crée une classe héritière de la classe Patchwork_PHP_Parser que l'on va nommer
Patchwork_PHP_Parser_NormalizerArray.
Il est conseillé d'avoir des variables et des fonctions "protected".

Déclaration des variables <code>$stack = array()</code> et <code>$callbacks =
array()</code> (si besoin relire la partie précente).

Création des méthodes avec la syntaxe suivante <code>protected function
tagOpenBracket</code> et <code>protected function tagCloseBracket</code>.

tagOpenBracket : <code>if($this->stack[] = T_VARIABLE !==
				$this->lastType))</code>

Si l'étiquette du token précédent est un T_VARIBABLE alors on ajoutera false à la
variable <code>$stack</code> sinon true. Il ne reste plus qu'à faire un
<code>return unshiftTokens(array(T_ARRAY, 'array'), '(');</code>

tagCloseBracket : <code>if($this->stack && array_pop($this->stack))</code>

on s'assure que $this->stack n'est pas vide et on regarde la dernière valeur du
tableau associé à la variable $stack. Si les deux conditions sont vérifiées on
n'a plus qu'à éxécuter le code suivant <code>return
$this->unshiftTokens(')');</code>

On s'assure d'avoir fermé toutes les parenthèses. Et voilà votre premier plugin
php patchwork prêt à l'emploi. bien sûr c'est un plugin assez simple, si vous
souhaitez en créer des plus compliqués n'hésitez pas à parcourir le fichier
**Parser.php** et d'observer le rôle en détail des méthodes implémentées.
