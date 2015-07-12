<script type="text/javascript"
        src="http://qzonestyle.gtimg.cn/qzone/openapi/qc_loader.js" data-appid="{$LOGIN_QQ_ID}" data-redirecturi="http://appletreesg.com" charset="utf-8"></script>

<script src="http://tjs.sjs.sinajs.cn/open/api/js/wb.js?appkey={$LOGIN_WEIBO_ID}&debug=true" type="text/javascript" charset="utf-8"></script>

<div class="col-12">
    <div>
        <span id="qqLoginBtn"></span>
        <script type="text/javascript">
            QC.Login({
                btnId:"qqLoginBtn"
            });
        </script>
    </div>
    <div>
        <div id="wb_connect_btn"></div>
        <script type="text/javascript">
            WB2.anyWhere(function (W) {
                W.widget.connectButton({
                    id: "wb_connect_btn",
                    type: '3,2',
                    callback: {
                        login: function (o) {
                            alert("login: " + o.screen_name)
                        },
                        logout: function () {

                        }
                    }
                });
            });
        </script>
    </div>
    <div>
        <a href="">
            <img src="" alt="Login as Alipay"/>
        </a>
    </div>
</div>