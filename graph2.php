<?php // content="text/plain; charset=utf-8"
require_once ('jpgraph/jpgraph.php');
require_once ('jpgraph/jpgraph_line.php');
require_once ('jpgraph/jpgraph_date.php');
require_once ('jpgraph/jpgraph_utils.inc.php');

$dbconn = pg_connect("dbname=ctdb user=ctdb_user host=localhost port=6544")
    or die('Could not connect: ' . pg_last_error());
$interval = 5;
$result = pg_query_params($dbconn, "SELECT subcount from submissions2 WHERE id % $1 = 0 AND subcount < 200 AND subcount > 0 ORDER by subcount DESC", array($interval))
	or die('Query failed: ' . pg_last_error());
$ydata = pg_fetch_all_columns($result, 0);
$xdata = range(0, pg_num_rows($result) * $interval - 1, $interval);
pg_free_result($result);

//$dateUtils = new DateScaleUtils();

// Setup a basic graph
$width=1024; $height=600;
$graph = new Graph($width, $height);
$graph->SetScale('linlin');
$graph->SetMargin(60,20,40,60);

// Setup the titles
$graph->title->SetFont(FF_ARIAL,FS_BOLD,12);
$graph->title->Set('Confidence');
//$graph->subtitle->SetFont(FF_ARIAL,FS_ITALIC,10);
//$graph->subtitle->Set('()');

// Setup the labels to be correctly format on the X-axis
$graph->xaxis->SetFont(FF_ARIAL,FS_NORMAL,8);
//$graph->xaxis->SetLabelAngle(30);

// The second paramter set to 'true' will make the library interpret the
// format string as a date format. We use a Month + Year format
// $graph->xaxis->SetLabelFormatString('M, Y',true);

// First add an area plot
$lp1 = new LinePlot($ydata,$xdata);
$lp1->SetWeight(0);
$lp1->SetFillColor('orange@0.85');
$graph->Add($lp1);

// And then add line. We use two plots in order to get a
// more distinct border on the graph
$lp2 = new LinePlot($ydata,$xdata);
$lp2->SetColor('orange');
$graph->Add($lp2);

// And send back to the client
$graph->Stroke();

?>
