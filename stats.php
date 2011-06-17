<?php include 'logo_start1.php'; ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8"/>
    <title>CTDB Statistics</title>
    <script type="text/javascript" src="http://www.google.com/jsapi"></script>
    <script type="text/javascript">
      google.load('visualization', '1', {packages: ['corechart']});
    </script>
    <script type="text/javascript">
      function drawSubmissionsStacked() {
	var xmlhttp = new XMLHttpRequest();
  	xmlhttp.open("GET", '/statsjson.php?type=submissions&stacked=1', false);
        xmlhttp.send();
        var data = new google.visualization.DataTable(xmlhttp.responseText);
        var ac = new google.visualization.AreaChart(document.getElementById('submissions_stacked'));
        ac.draw(data, {
          title : 'Cummulative submissions', // ' since' 
          isStacked: 'true',
          width: 800,
          height: 400,
          vAxis: {title: "Submissions"},
          hAxis: {title: "Day"}
        });
      }
      function drawSubmissions() {
	var xmlhttp = new XMLHttpRequest();
  	xmlhttp.open("GET", '/statsjson.php?type=submissions', false);
        xmlhttp.send();
        var data = new google.visualization.DataTable(xmlhttp.responseText);
        var ac = new google.visualization.AreaChart(document.getElementById('submissions'));
        ac.draw(data, {
          title : 'Daily submissions',
          isStacked: 'false',
          width: 800,
          height: 400,
          vAxis: {title: "Submissions"},
          hAxis: {title: "Day"}
        });
      };
      function drawDrives() {
        var xmlhttp = new XMLHttpRequest();
        xmlhttp.open("GET", '/statsjson.php?type=drives', false);
        xmlhttp.send();
        var data = new google.visualization.DataTable(xmlhttp.responseText);
        new google.visualization.PieChart(document.getElementById('drives')).
          draw(data, {title:"Drives", is3D : true, pieSliceText : 'label', legend : 'none', width : 400, height : 400});
      };
      function drawAgents() {
        var xmlhttp = new XMLHttpRequest();
        xmlhttp.open("GET", '/statsjson.php?type=agents', false);
        xmlhttp.send();
        var data = new google.visualization.DataTable(xmlhttp.responseText);
        new google.visualization.PieChart(document.getElementById('agents')).
          draw(data, {title:"Agents", is3D : true, pieSliceText : 'label', legend : 'none', width : 400, height : 400});
      };
      function drawPregaps() {
        var xmlhttp = new XMLHttpRequest();
        xmlhttp.open("GET", '/statsjson.php?type=pregaps', false);
        xmlhttp.send();
        var data = new google.visualization.DataTable(xmlhttp.responseText);
        new google.visualization.PieChart(document.getElementById('pregaps')).
          draw(data, {title:"Pregaps", is3D : true, pieSliceText : 'label', legend : 'none', width : 400, height : 400});
      };
      google.setOnLoadCallback(drawSubmissions);
      google.setOnLoadCallback(drawSubmissionsStacked);
      google.setOnLoadCallback(drawDrives);
      google.setOnLoadCallback(drawAgents);
      google.setOnLoadCallback(drawPregaps);
    </script>
  </head>
  <?php include 'logo_start2.php'; ?>
    <center>
    <div id="submissions"></div>
    <div id="submissions_stacked"></div>
    <div id="drives"></div>
    <div id="agents"></div>
    <div id="pregaps"></div>
    </center>
  </body>
</html>
