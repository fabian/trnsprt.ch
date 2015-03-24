$(function () {

    if (navigator.geolocation) {

        if (!$('input[name=from]').val()) {

            $('input[name=from]').attr('placeholder', 'Locating...');

            var i = 0;
            var interval = setInterval(function () {
                i = (i + 1) % 4;
                var message = 'Locating';
                for (var j = 0; j < i; j++) {
                    message += '.';
                }
                $('input[name=from]').attr('placeholder', message);
            }, 400);

            // get location for from
            navigator.geolocation.getCurrentPosition(function (position) {

                var lat = position.coords.latitude;
                var lng = position.coords.longitude;

                $.get('http://transport.opendata.ch/v1/locations', {x: lat, y: lng}, function(data) {

                    clearInterval(interval);
                    $('input[name=from]').attr('placeholder', 'From');

                    $(data.stations).each(function (i, station) {

                        $('input[name=from]').attr('placeholder', station.name);

                        return false;
                    });
                });

            }, function(error) {

                clearInterval(interval);
                $('input[name=from]').attr('placeholder', 'From');

            }, {
                enableHighAccuracy:true,
                timeout: 10000,
                maximumAge: 0
            });
        }
    }

    if (!$('input[name=to]').val()) {
        $('input[name=to]').focus();
    }

    // datetime for anyone else than iPhone
    if (navigator.userAgent.match(/iPhone/i) == null) {

        var datetime = $('input[name=datetime]');
        datetime.hide();
        datetime.prop('type', 'text');

        var date = $('<input type="text" class="date" name="date" placeholder="Date (optional)" tabindex="3" />');
        datetime.after(date);
        date.val(datetime.val().substring(0, 10));

        var time = $('<input type="text" class="time" name="time" placeholder="Time" tabindex="4" />');
        date.after(time);
        time.val(datetime.val().substring(11, 16));

        $('input[name=date], input[name=time]').bind('change', function() {
            datetime.val(date.val() + ' ' + time.val());
        });
    }

    function reset() {
        $('table.connections tr.connection').show();
        $('table.connections tr.section').hide();
    }

    $('table.connections tr.connection').bind('click', function (e) {

        reset();

        var $this = $(this);
        $this.hide();
        $this.nextAll('tr.section').show();

        if ('replaceState' in window.history) {
            history.replaceState(
                {},
                '',
                TRNSPRT_CONNECTIONS_URL + '&c=' + $this.data('c')
            )
        }
    });

    $('.station input').bind('focus', function () {
        var that = this;
        setTimeout(function () {
            that.setSelectionRange(0, 9999);
        }, 10);
    });

    $('form').submit(function (e) {
        var from = $('input[name=from]').val(),
            placeholder = $('input[name=from]').attr('placeholder');

        if (!from && placeholder != 'From' && placeholder.substring(0, 8) != 'Locating') {
            from = placeholder;
        }

        var to = $('input[name=to]').val(),
            datetime = $('input[name=datetime]').val(),
            url =
                '/to/' +  to +
                (from ? '/from/' + from : '') +
                (datetime ? '/at/' + datetime : '');

        e.preventDefault();
        location.replace(url);
    });
    
    $('.station input').typeahead({
        minLength: 2,
        items: 6,
        source: function (query, process) {
            return $.get('http://transport.opendata.ch/v1/locations', {query: query, type: 'station'}, function(data) {
                if (data.stations.length == 1 && data.stations[0].name == query) {
                    return;
                }
                process($.map(data.stations, function(station) {
                    return station.name;
                }));
            }, 'json');
        },
        highlighter: function(item) {
            return item;
        },
        matcher: function () {
            return true;
        }
    });
});
