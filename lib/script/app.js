/**
 * Created by oswald on 16.02.2015.
 */
var app = angular.module("raidplanerApp", ['ngRoute', 'pascalprecht.translate']);

app.config(function ($routeProvider, $httpProvider) {
    $httpProvider.defaults.withCredentials = true;

    $routeProvider.when('/calendar', {
        resolve    : {
            gLocale: function ($http) {
                return $http.post('lib/messagehub.php', { Action: 'query_locale' }).then(function (response) {
                    return response.data.locale;
                })
            },
            gConfig: function ($http) {
                return $http.post('lib/messagehub.php', { Action: 'query_config' }).then(function (response) {
                    return response.data;
                })
            },
            gUser: function ($http, $location) {
                return $http.post('lib/messagehub.php', { Action: 'query_user' }).then(function (response) {
                    if (response.data.registeredUser === false) {
                        $location.path("/login");
                    }
                    return response.data;
                })
            }
        },
        controller : 'CalendarController',
        templateUrl: 'lib/script/templates/calendar.tpl.html'
    }).when('/login', {
        resolve    : {
            gLocale: function ($http) {
                return $http.post('lib/messagehub.php', {Action: 'query_locale'}).then(function (response) {
                    return response.data.locale;
                })
            },
            gConfig: function ($http) {
                return $http.post('lib/messagehub.php', { Action: 'query_config' }).then(function (response) {
                    return response.data;
                })
            }
        },
        controller: 'LoginController',
        templateUrl: 'lib/script/templates/login.tpl.html'
    }).otherwise({
        redirectTo: '/calendar'
    });
});

//app.service('gLocale', ['$http', '$q', function gLocale($http, $q) {
//    var self = this;
//    self.locales = null;
//
//    self.get = function() {
//        var def = $q.defer();
//
//        if (self.locales !== null) {
//            def.resolve(self.locales);
//        } else {
//            $http({
//                url: 'lib/messagehub.php',
//                method: 'POST',
//                data: { 'Action': 'query_locale' },
//                // @todo this option is to preserve compability with messagehub.php - remove later
//                transformRequest: function(obj) {
//                    var str = [];
//                    for(var p in obj) {
//                        str.push(encodeURIComponent(p) + "=" + encodeURIComponent(obj[p]));
//                    }
//                    return str.join("&");
//                }
//            }).then(function (response) {
//                self.locales = response.data.locale;
//                def.resolve(response.data.locale);
//            });
//        }
//
//        return def.promise;
//    }
//}]);

app.controller('MenuController', ['$scope', '$rootScope', function($scope, $rootScope) {

}]);

app.controller('CalendarController', ['$scope', '$rootScope', '$http', 'gLocale', 'gConfig', 'gUser', function($scope, $rootScope, $http, gLocale, gConfig, gUser) {
    $rootScope.gLocale = gLocale;
    $rootScope.gSite = gConfig.site;
    $rootScope.gGame = gConfig.game;
    $rootScope.gUser = gUser;

    // @todo refactor
    startFadeTooltip();
    closeSheet();

    $scope.WeekDayArray = [ gLocale.Sunday, gLocale.Monday, gLocale.Tuesday, gLocale.Wednesday,
        gLocale.Thursday, gLocale.Friday, gLocale.Saturday ];
    $scope.MonthArray   = [ gLocale.January, gLocale.February, gLocale.March, gLocale.April,
        gLocale.May, gLocale.June, gLocale.July, gLocale.August, gLocale.September,
        gLocale.October, gLocale.November, gLocale.December ];

    $scope.calendarInitialized = false;

    $scope.loadCalendarUnchecked = function(aMonthBase0, aYear, aOffset) {
        var ShowYear  = aYear;
        var ShowMonth = aMonthBase0 + aOffset;

        while (ShowMonth < 0)
        {
            --ShowYear;
            ShowMonth += 12;
        }

        while (ShowMonth > 11)
        {
            ++ShowYear;
            ShowMonth -= 12;
        }

        var postData = {
            Action     : 'query_calendar',
            Month      : ShowMonth+1,
            Year       : ShowYear
        };

        $http.post('lib/messagehub.php', postData).then(function(response){

            var StartDay    = parseInt( response.data.startDay, 10 );
            var StartMonth  = parseInt( response.data.startMonth, 10 ) - 1;
            var StartYear   = parseInt( response.data.startYear, 10 );
            var StartOfWeek = parseInt( response.data.startOfWeek, 10 );

            $scope.ActiveMonth = parseInt( response.data.displayMonth, 10 ) - 1;
            $scope.ActiveYear  = parseInt( response.data.displayYear, 10 );

            // generateCalendar
            var TimeIter    = new Date(StartYear, StartMonth, StartDay);
            var Today = new Date();

            $scope.raid    = response.data.raid;

            $scope.weeks = [];
            for (weekIdx=0;weekIdx<6;weekIdx++) {
                var week = [];

                for (dayIdx=0;dayIdx<7;dayIdx++) {
                    TimeIter.setDate(TimeIter.getDate() + 1);

                    var date = new Date();
                    date.setTime(TimeIter.getTime());
                    week.push({
                        date: date,
                        today: Today,
                        isToday: (Today.getMonth() == date.getMonth() ) && ( Today.getDate() == date.getDate())
                    });
                }

                $scope.weeks.push(week);
            }

            $scope.calendarInitialized = true;
        });
    };



    if ( gUser.calendar != null )
    {
        $scope.loadCalendarUnchecked(gUser.calendar.month-1, gUser.calendar.year, 0);
    }
    else
    {
        $scope.loadCalendarUnchecked( $scope.Today.getUTCMonth(), $scope.Today.getUTCFullYear(), 0 );
    }

}]);

app.controller('LoginController', ['$scope', '$rootScope', '$http', '$location', 'gLocale', 'gConfig', function($scope, $rootScope, $http, $location, gLocale, gConfig) {
    $rootScope.gLocale = gLocale;
    $rootScope.gSite = gConfig.site;
    $rootScope.gGame = gConfig.game;
    $scope.loginInProgress = false;

    $scope.startLogin = function() {
        if (!$scope.loginname || $scope.loginInProgress) {
            return false;
        }
        $scope.loginInProgress = true;

        var postData = {
            Action: 'query_credentials',
            Login: $scope.loginname
        };

        $http.post('lib/messagehub.php', postData).then(function(response){
            if (response.data.error && response.data.error !== null && response.data.error.length > 0) {
                // @todo refactor
                notify(response.data.error);
                return;
            }

            var Salt   = response.data.salt;
            var Key    = response.data.pubkey;
            var Method = response.data.method;
            var Pass   = $scope.loginpass;

            // @todo refactor
            hash( Key, Method, Pass, Salt, updateProgress, function(aEncodedPass) {

                var postData = {
                    Action : 'login',
                    user   : $scope.loginname,
                    pass   : aEncodedPass,
                    sticky : $("#sticky_value").val() == "true"
                };

                $http.post( 'lib/messagehub.php', postData).then(function(response) {
                    if ( (response.data.error != null) && (response.data.error.length > 0) )
                    {
                        // @todo refactor
                        notify(response.data.error);
                        $scope.loginInProgress = false;
                    }
                    else
                    {
                        // @todo return to einbauen
                        $location.path('/calendar');
                    }
                }, true);
            });



            $scope.loginInProgress = false;
        });
    }

}]);


app.run(['$rootScope', '$http', '$location', function ($rootScope, $http, $location) {
    $http.defaults.headers.post["Content-Type"] = "application/x-www-form-urlencoded";
    // @todo this option is to preserve compability with messagehub.php - remove later
    $http.defaults.transformRequest = function(obj) {
        var str = [];
        for(var p in obj) {
            str.push(encodeURIComponent(p) + "=" + encodeURIComponent(obj[p]));
        }
        return str.join("&");
    };


    $rootScope.gLocale = null;
    $rootScope.gSite = null;
    $rootScope.gGame = null;
    $rootScope.gUser = null;
}]);
