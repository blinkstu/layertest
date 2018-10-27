var socket_uri = "https://c6pu.com:2120";
$.ajax({
  method: "POST",
  url: "/api/admin/runtime/getRuntimeServer",
  async: false
})
  .done(function(data) {
    socket_uri = data + ":2120";
  })
  .fail(function(data) {
    alert("获取调度服务器地址失败，请联系开发人员");
  });
//const socket_uri = "https://192.168.100.6:2120";
