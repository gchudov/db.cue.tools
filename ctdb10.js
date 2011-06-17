function decimalToHexString(number)
{
  var hex = (number < 0 ?  0xFFFFFFFF + number + 1 : number).toString(16).toUpperCase();
  return "00000000".substr(0, 8 - hex.length) + hex;
};

function pad2(n)
{
  return (n < 10 ? '0' : '') + n;
};

function ctdbEntryData(json)
{
  var data = new google.visualization.DataTable(json);
  for (var row = 0; row < data.getNumberOfRows(); row++) {
    var artist = data.getValue(row, 0);
    if (!artist) artist = "Unknown Artist";
    data.setFormattedValue(row, 0, '<a href="?artist=' + encodeURIComponent(artist) + '">' + artist.substring(0,50) + '</a>');
    var title = data.getValue(row, 1);
    if (!title) title = "Unknown Title";
    data.setFormattedValue(row, 1, title.substring(0,60));
    var toc = data.getValue(row, 2);
    data.setFormattedValue(row, 2, '<a href="?tocid=' + toc + '">' + toc + '</a>');
    data.setFormattedValue(row, 4, '<a href="show.php?id=' + data.getValue(row, 4).toString(10) + '">' + decimalToHexString(data.getValue(row, 6)) + '</a>');
    data.setProperty(row, 2, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
    data.setProperty(row, 3, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
    data.setProperty(row, 4, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
    data.setProperty(row, 5, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
  }
  return data;
};

function ctdbSubmissionData(json)
{
  var data = new google.visualization.DataTable(json);
  for (var row = 0; row < data.getNumberOfRows(); row++) {
    if (row == 75) document.getElementById('submissions_div').innerHTML += '.' + row;
    var dt = new Date(data.getValue(row, 0)*1000);
    var dtnow = new Date();
    var dtstring = (dtnow - dt > 1000*60*60*24 ? dt.getFullYear()
      + '-' + pad2(dt.getMonth()+1)
      + '-' + pad2(dt.getDate())
      + ' ' : '') + pad2(dt.getHours())
      + ':' + pad2(dt.getMinutes())
      + ':' + pad2(dt.getSeconds());
    data.setFormattedValue(row, 0, dtstring);
    data.setProperty(row, 0, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
    data.setProperty(row, 1, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
    var matches = data.getValue(row, 1).match(/(CUETools|CUERipper|EACv.* CTDB) ([\d\.]*)/);
    var img = matches == null ? '' : matches[1] == 'CUETools' ? 'cuetools.png' :  matches[1] == 'CUERipper' ? 'cueripper.png' : matches[1] == 'EACv1.0b2 CTDB' ? 'eac.png' : ''; 
    data.setFormattedValue(row, 1, (img != '' ? '<img height=12 src="' + img + '">' : '') + '<a href="?agent=' + data.getValue(row, 1) + '">' + (matches == null ? '?' : matches[2]) + '</a>');
    data.setProperty(row, 2, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
    data.setFormattedValue(row, 2, '<a href="?drivename=' + encodeURIComponent(data.getValue(row, 2)) + '">' + data.getValue(row, 2).substring(0,20) + '</a>');
    data.setProperty(row, 3, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
    data.setFormattedValue(row, 3, '<a href="?uid=' + data.getValue(row, 3) + '">' + data.getValue(row, 3).substring(0,6) + '</a>');
    data.setProperty(row, 4, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
    var artist = data.getValue(row, 5);
    if (!artist) artist = "Unknown Artist";
    data.setFormattedValue(row, 5, '<a href="?artist=' + encodeURIComponent(artist) + '">' + artist.substring(0,30) + '</a>');
    var title = data.getValue(row, 6);
    if (!title) title = "Unknown Title";
    data.setFormattedValue(row, 6, title.substring(0,30));
    data.setProperty(row, 7, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
    var toc = data.getValue(row, 7);
    data.setFormattedValue(row, 7, '<a href="?tocid=' + toc + '">' + toc.substring(0,7) + '</a>');
    data.setProperty(row, 8, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
    data.setProperty(row, 9, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
    data.setFormattedValue(row, 9, '<a href="show.php?id=' + data.getValue(row, 9).toString(10) + '">' + decimalToHexString(data.getValue(row, 11)) + '</a>');
    data.setProperty(row, 10, 'className', 'google-visualization-table-td google-visualization-table-td-consolas');
    document.getElementById('submissions_div').innerHTML += '.';
  }
  return data;
};
