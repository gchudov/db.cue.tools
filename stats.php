<?php include 'logo_start1.php'; ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8"/>
    <title>CTDB Statistics</title>
    <script type="text/javascript"
      src='https://www.google.com/jsapi?autoload={"modules":[{"name":"visualization","version":"1"}]}'>
    </script>
    <script type="text/javascript">
      function drawSubmissions() {
        var wrapper = new google.visualization.ChartWrapper({
          chartType: 'AreaChart',
          dataSourceUrl: '/statsjson.php?type=submissions&count=365',
          options: {
            title : 'Daily submissions',
            isStacked: 'false',
            width: 1200,
            height: 350,
            fontSize: 12,
            vAxis: {title: "Submissions"},
            hAxis: {title: "Day"}
          },
          containerId: 'submissions'
        });
        wrapper.draw();
      };
      function drawSubmissionsHourly() {
        var wrapper = new google.visualization.ChartWrapper({
          chartType: 'AreaChart',
//          chartType: 'LineChart',
//          refreshInterval: 1,
          dataSourceUrl: '/statsjson.php?type=submissions&count=336&hourly=1',
          options: {
            title : 'Hourly submissions', // ' since'
            isStacked: 'false',
//            curveType: 'function',
            width: 1200,
            height: 350,
            fontSize: 12,
            vAxis: {title: "Submissions"},
            hAxis: {title: "Hour"}
          },
          containerId: 'submissions_hourly'
        });
//        google.visualization.events.addListener(wrapper, 'ready', onReady);
        wrapper.draw();
//  function onReady() {
//    alert(wrapper.getRefreshInterval());
//  }
      }
      function drawSubmissionsStacked() {
        var wrapper = new google.visualization.ChartWrapper({
          chartType: 'AreaChart',
          dataSourceUrl: '/statsjson.php?type=submissions&hourly=1&count=200&stacked=1',
          options: {
            title : 'Cummulative submissions', // ' since' 
            isStacked: 'true',
            width: 1200,
            height: 350,
            fontSize: 12,
            vAxis: {title: "Submissions"},
            hAxis: {title: "Day"}
          },
          containerId: 'submissions_stacked'
        });
        wrapper.draw();
      }
      function drawDrives() {
        var wrapper = new google.visualization.ChartWrapper({ chartType: 'PieChart', dataSourceUrl: '/statsjson.php?type=drives',
          options: {title:"Drives", is3D : true, pieSliceText : 'label', width : 400, height : 400, fontSize : 9, chartArea : {left:'5%',top:'10%',width:'90%',height:'80%'}},
          //options: {title:"Drives", is3D : true, pieSliceText : 'label', legend : 'none', width : 400, height : 400, fontSize : 9},
          containerId: 'drives'
        });
        wrapper.draw();
      };
      function drawAgents() {
        var wrapper = new google.visualization.ChartWrapper({ chartType: 'PieChart', dataSourceUrl: '/statsjson.php?type=agents',
          options: {title:"Agents", is3D : true, pieSliceText : 'label', width : 400, height : 400, fontSize : 9, chartArea : {left:'5%',top:'10%',width:'90%',height:'80%'}},
          //options: {title:"Agents", is3D : true, pieSliceText : 'label', legend : 'none', width : 400, height : 400, fontSize : 9},
          containerId: 'agents'
        });
        wrapper.draw();
      };
      function drawPregaps() {
        var wrapper = new google.visualization.ChartWrapper({ chartType: 'PieChart', dataSourceUrl: '/statsjson.php?type=pregaps',
          options: {title:"Pregaps", is3D : true, pieSliceText : 'label', width : 400, height : 400, fontSize : 9, chartArea : {left:'5%',top:'10%',width:'90%',height:'80%'}},
          //options: {title:"Pregaps", is3D : true, pieSliceText : 'label', legend : 'none', width : 400, height : 400, fontSize : 9},
          containerId: 'pregaps'
        });
        wrapper.draw();
      };
      google.setOnLoadCallback(drawSubmissions);
      google.setOnLoadCallback(drawSubmissionsHourly);
      //google.setOnLoadCallback(drawSubmissionsStacked);
      google.setOnLoadCallback(drawDrives);
      google.setOnLoadCallback(drawAgents);
      google.setOnLoadCallback(drawPregaps);
    </script>
  </head>
  <?php include 'logo_start2.php'; ?>
    <center>
    <div id="submissions"></div>
    <div id="submissions_hourly"></div>
    <div id="submissions_stacked"></div>
    <span id="drives"></span>
    <span id="agents"></span>
    <span id="pregaps"></span>
    </center>
  </body>
</html>
