angular.module('app.main', [])
	// initiate body
	.directive('body', function() {
		return {
			restrict: 'E',
			link: function(scope, element, attrs) {
				element.on('click', 'a[href="#"], [data-toggle]', function(e) {
					e.preventDefault();
				})
			}
		}
	})
	.directive('fileModel', ['$parse', function ($parse) {
		return {
		    restrict: 'A',
		    link: function(scope, element, attrs) {
		        var model = $parse(attrs.fileModel);
		        var modelSetter = model.assign;

		        element.bind('change', function(){
		            scope.$apply(function(){
		                modelSetter(scope, element[0].files[0]);
		            });
		        });
		    }
		};}])

	.filter('nodash', function () {
		return function (text) {
			return text.toString().replace('-', '');
		};
	})

    .filter('productViewUrl', function ($sce) {
        return function(id) {
            return $sce.trustAsResourceUrl('/products/view/' + id);
        };
    })

	.directive('ngChangeOnBlur', function() {
		return {
			restrict: 'A',
			require: 'ngModel',
			link: function(scope, elm, attrs, ngModelCtrl) {
				if (attrs.type === 'radio' || attrs.type === 'checkbox')
					return;

				var expressionToCall = attrs.ngChangeOnBlur;

				var oldValue = null;
				elm.bind('focus',function() {
					scope.$apply(function() {
						oldValue = elm.val();
						console.log(oldValue);
					});
				})
				elm.bind('blur', function() {
					scope.$apply(function() {
						var newValue = elm.val();
						console.log(newValue);
						if (newValue != oldValue){
							scope.$eval(expressionToCall);
						}
						//alert('changed ' + oldValue);
					});
				});
			}
		};
	})

    .directive('ngNumbers', function()
    {
        return {
            require: 'ngModel',
            link: function(scope, element, attrs, modelCtrl)
            {
                modelCtrl.$parsers.push(function (inputValue)
                {
                    if (inputValue == undefined) return '';
                    var transformedInput = inputValue.replace(/[^0-9]/g, '');
                    console.log(transformedInput);
                    if (transformedInput!=inputValue)
                    {
                        modelCtrl.$setViewValue(transformedInput);
                        modelCtrl.$render();
                    }

                    return transformedInput;
                });
            }
        };
    })

    .directive('ngUpperletters', function()
    {
        return {
            require: 'ngModel',
            link: function(scope, element, attrs, modelCtrl)
            {
                modelCtrl.$parsers.push(function (inputValue)
                {
                    if (inputValue == undefined) return ''
                    var transformedInput = inputValue.toUpperCase().replace(/[^A-Z]/g, '');
                    if (transformedInput!=inputValue)
                    {
                        modelCtrl.$setViewValue(transformedInput);
                        modelCtrl.$render();
                    }

                    return transformedInput;
                });
            }
        };
    })

    .directive('ngEnter', function ()
    {
        return function (scope, element, attrs)
        {
            element.bind("keydown keypress", function (event)
            {
                if(event.which === 13)
                {
                    scope.$apply(function ()
                    {
                        scope.$eval(attrs.ngEnter);
                    });

                    event.preventDefault();
                }
            });
        };
    })

    .directive('ngConfirmClick',
        [
            function()
            {
                return {
                    priority: -1,
                    restrict: 'A',
                    link: function(scope, element, attrs)
                    {
                        element.bind('click', function(e)
                        {
                            var message = attrs.ngConfirmClick;
                            if(message && !confirm(message))
                            {
                                e.stopImmediatePropagation();
                                e.preventDefault();
                            }
                        });
                    }
                };
            }
        ]
    )

    .filter('nl2br', function ($sce) {
        return function (text) {
            return text ? $sce.trustAsHtml(text.replace(/\n/g, '<br/>')) : '';
        };
    })

	.directive('ngRightClick', function($parse) {
		return function(scope, element, attrs) {
			var fn = $parse(attrs.ngRightClick);
			element.bind('contextmenu', function(event) {
				scope.$apply(function() {
					event.preventDefault();
					fn(scope, {$event:event});
				});
			});
		};
	})

	.directive('action', function() {
		return {
			restrict: 'A',
			link: function(scope, element, attrs) {
				/*
				 * SMART ACTIONS
				 */
				var smartActions = {

				    // LAUNCH FULLSCREEN 
				    launchFullscreen: function(element){
				
						if (!$.root_.hasClass("full-screen")) {
					
							$.root_.addClass("full-screen");
					
							if (element.requestFullscreen) {
								element.requestFullscreen();
							} else if (element.mozRequestFullScreen) {
								element.mozRequestFullScreen();
							} else if (element.webkitRequestFullscreen) {
								element.webkitRequestFullscreen();
							} else if (element.msRequestFullscreen) {
								element.msRequestFullscreen();
							}
					
						} else {
							
							$.root_.removeClass("full-screen");
							
							if (document.exitFullscreen) {
								document.exitFullscreen();
							} else if (document.mozCancelFullScreen) {
								document.mozCancelFullScreen();
							} else if (document.webkitExitFullscreen) {
								document.webkitExitFullscreen();
							}
					
						}
				
				   },
				
				   // MINIFY MENU
				    minifyMenu: function($this){
				    	if (!$.root_.hasClass("menu-on-top")){
							$.root_.toggleClass("minified");
							$.root_.removeClass("hidden-menu");
							$('html').removeClass("hidden-menu-mobile-lock");
							$this.effect("highlight", {}, 500);
						}
				    },
				    
				    // TOGGLE MENU 
				    toggleMenu: function(){
				    	if (!$.root_.hasClass("menu-on-top")){
							$('html').toggleClass("hidden-menu-mobile-lock");
							$.root_.toggleClass("hidden-menu");
							$.root_.removeClass("minified");
				    	} else if ( $.root_.hasClass("menu-on-top") && $.root_.hasClass("mobile-view-activated") ) {
				    		$('html').toggleClass("hidden-menu-mobile-lock");
							$.root_.toggleClass("hidden-menu");
							$.root_.removeClass("minified");
				    	}
				    }
				};
				
				var actionEvents = {
					userLogout: function(e) {
						smartActions.userLogout(element);
					},
					resetWidgets: function(e) {
						smartActions.resetWidgets(element);
					},
					launchFullscreen: function(e) {
						smartActions.launchFullscreen(document.documentElement);
					},
					minifyMenu: function(e) {
						smartActions.minifyMenu(element);
					},
					toggleMenu: function(e) {
						smartActions.toggleMenu();
					},
					toggleShortcut: function(e) {
						smartActions.toggleShortcut();
					}
				};

				if (angular.isDefined(attrs.action) && attrs.action != '') {
					var actionEvent = actionEvents[attrs.action];
					if (typeof actionEvent === 'function') {
						element.on('click', function(e) {
							actionEvent(e);
							e.preventDefault();
						});
					}
				}

			}
		};
	})

	.directive('header', function() {
		return {
			restrict: 'EA',
			link: function(scope, element, attrs) {
				// SHOW & HIDE MOBILE SEARCH FIELD
				angular.element('#search-mobile').click(function() {
					$.root_.addClass('search-mobile');
				});

				angular.element('#cancel-search-js').click(function() {
					$.root_.removeClass('search-mobile');
				});
			}
		};
	})
;

// directives for navigation
angular.module('app.navigation', [])
	.directive('navigation', function() {
		return {
			restrict: 'AE',
			controller: ['$scope', function($scope) {

			}],
			transclude: true,
			replace: true,
			link: function(scope, element, attrs) {
				if (!topmenu) {
					if (!null) {
						element.first().jarvismenu({
							accordion : true,
							speed : $.menu_speed,
							closedSign : '<em class="fa fa-plus-square-o"></em>',
							openedSign : '<em class="fa fa-minus-square-o"></em>'
						});
					} else {
						alert("Error - menu anchor does not exist");
					}
				}

				// SLIMSCROLL FOR NAV
				if ($.fn.slimScroll) {
					element.slimScroll({
				        height: '100%'
				    });
				}

				scope.getElement = function() {
					return element;
				}
			},
			template: '<nav><ul data-ng-transclude=""></ul></nav>'
		};
	})

	.controller('NavGroupController', ['$scope', function($scope) {
		$scope.active = false;
		$scope.hasIcon = angular.isDefined($scope.icon);
	    $scope.hasIconCaption = angular.isDefined($scope.iconCaption);

	    this.setActive = function(active) {
	    	$scope.active = active;
	    };
	}])
	.directive('navGroup', function() {
		return {
			restrict: 'AE',
			controller: 'NavGroupController',
			transclude: true,
			replace: true,
			scope: {
				icon: '@',
				title: '@',
				iconCaption: '@',
				active: '=?'
			},
			template: '\
				<li data-ng-class="{active: active}">\
					<a href="">\
						<i data-ng-if="hasIcon" class="{{ icon }}"><em data-ng-if="hasIconCaption"> {{ iconCaption }} </em></i>\
						<span class="menu-item-parent">{{ title }}</span>\
					</a>\
					<ul data-ng-transclude=""></ul>\
				</li>'

		};
	})

	.controller('NavItemController', ['$rootScope', '$scope', '$location', function($rootScope, $scope, $location) {
		$scope.isChild = false;
		$scope.active = false;
		$scope.isActive = function (viewLocation) {
	        $scope.active = viewLocation === $location.path();
	        return $scope.active;
	    };

	    $scope.hasIcon = angular.isDefined($scope.icon);
	    $scope.hasIconCaption = angular.isDefined($scope.iconCaption);

	    $scope.getItemUrl = function(view) {
	    	if (angular.isDefined($scope.href)) return $scope.href;
	    	if (!angular.isDefined(view)) return '';
	    	return '#' + view;
	    };

	    $scope.getItemTarget = function() {
	    	return angular.isDefined($scope.target) ? $scope.target : '_self';
	    };

	}])
	.directive('navItem', ['$window', function($window) {
		return {
			require: ['^navigation', '^?navGroup'],
			restrict: 'AE',
			controller: 'NavItemController',
			scope: {
				title: '@',
				view: '@',
				icon: '@',
				iconCaption: '@',
				href: '@',
				target: '@'
			},
			link: function(scope, element, attrs, parentCtrls) {
				var navCtrl = parentCtrls[0],
					navgroupCtrl = parentCtrls[1];

				scope.$watch('active', function(newVal, oldVal) {
					if (newVal) {
						if (angular.isDefined(navgroupCtrl)) navgroupCtrl.setActive(true);
						$window.document.title = scope.title;
					} else {
						if (angular.isDefined(navgroupCtrl)) navgroupCtrl.setActive(false);
					}
				});

				scope.openParents = scope.isActive(scope.view);
				scope.isChild = angular.isDefined(navgroupCtrl);

	    		element.on('click', 'a[href!="#"]', function() {
	    			if ($.root_.hasClass('mobile-view-activated')) {
	    				$.root_.removeClass('hidden-menu');
	    				$('html').removeClass("hidden-menu-mobile-lock");
	    			}
	    		});
				
			},
			transclude: true,
			replace: true,
			template: '\
				<li data-ng-class="{active: isActive(view)}">\
					<a href="{{ getItemUrl(view) }}" target="{{ getItemTarget() }}" title="{{ title }}">\
						<i data-ng-if="hasIcon" class="{{ icon }}"><em data-ng-if="hasIconCaption"> {{ iconCaption }} </em></i>\
						<span ng-class="{\'menu-item-parent\': !isChild}"> {{ title }} </span>\
						<span data-ng-transclude=""></span>\
					</a>\
				</li>'
		};
	}])
;

angular.module('app.smartui', [])
	.directive('widgetGrid', function() {
		return {
			restrict: 'AE',
			link: function(scope, element, attrs) {
				scope.setup_widget_desktop = function() {
					if ($.fn.jarvisWidgets && $.enableJarvisWidgets) {
						element.jarvisWidgets({
							grid : 'article',
							widgets : '.jarviswidget',
							localStorage : false,
							deleteSettingsKey : '#deletesettingskey-options',
							settingsKeyLabel : 'Reset settings?',
							deletePositionKey : '#deletepositionkey-options',
							positionKeyLabel : 'Reset position?',
							sortable : false,
							buttonsHidden : true,
							// toggle button
							toggleButton : false,
							toggleClass : 'fa fa-minus | fa fa-plus',
							toggleSpeed : 200,
							onToggle : function() {
							},
							// delete btn
							deleteButton : false,
							deleteClass : 'fa fa-times',
							deleteSpeed : 200,
							onDelete : function() {
							},
							// edit btn
							editButton : false,
							editPlaceholder : '.jarviswidget-editbox',
							editClass : 'fa fa-cog | fa fa-save',
							editSpeed : 200,
							onEdit : function() {
							},
							// color button
							colorButton : false,
							// full screen
							fullscreenButton : true,
							fullscreenClass : 'fa fa-expand | fa fa-compress',
							fullscreenDiff : 3,
							onFullscreen : function() {
							},
							// custom btn
							customButton : false,
							customClass : 'folder-10 | next-10',
							customStart : function() {
								alert('Hello you, this is a custom button...');
							},
							customEnd : function() {
								alert('bye, till next time...');
							},
							// order
							buttonOrder : '%refresh% %custom% %edit% %toggle% %fullscreen% %delete%',
							opacity : 1.0,
							dragHandle : '> header',
							placeholderClass : 'jarviswidget-placeholder',
							indicator : false,
							indicatorTime : 600,
							ajax : true,
							timestampPlaceholder : '.jarviswidget-timestamp',
							timestampFormat : 'Last update: %m%/%d%/%y% %h%:%i%:%s%',
							refreshButton : false,
							refreshButtonClass : 'fa fa-refresh',
							labelError : 'Sorry but there was a error:',
							labelUpdated : 'Last Update:',
							labelRefresh : 'Refresh',
							labelDelete : 'Delete widget:',
							afterLoad : function() {
							},
							rtl : false, // best not to toggle this!
							onChange : function() {

							},
							onSave : function() {

							},
							ajaxnav : $.navAsAjax // declears how the localstorage should be saved (HTML or AJAX page)

						});
					}
				}

				scope.setup_widget_mobile = function() {
					if ($.enableMobileWidgets && $.enableJarvisWidgets) {
						scope.setup_widgets_desktop();
					}
				}

				if ($.device === "desktop") {
					scope.setup_widget_desktop();
				} else {
					scope.setup_widget_mobile();
				}
				
			}
		}
	})

	.directive('widget', function() {
		return {
			restrict: 'AE',
			transclude: true,
			replace: true,
			template: '<div class="jarviswidget" data-ng-transclude=""></div>'
		}
	})

	.directive('widgetHeader', function() {
		return {
			restrict: 'AE',
			transclude: true,
			replace: true,
			scope: {
				title: '@',
				icon: '@'
			},
			template: '\
				<header>\
					<span class="widget-icon"> <i data-ng-class="icon"></i> </span>\
					<h2>{{ title }}</h2>\
					<span data-ng-transclude></span>\
				</header>'
		}
	})

	.directive('widgetToolbar', function() {
		return {
			restrict: 'AE',
			transclude: true,
			replace: true,
			template: '<div class="widget-toolbar" data-ng-transclude=""></div>'
		}
	})

	.directive('widgetEditbox', function() {
		return {
			restrict: 'AE', 
			transclude: true, 
			replace: true,
			template: '<div class="jarviswidget-editbox" data-ng-transclude=""></div>'
		}
	})

	.directive('widgetBody', function() {
		return {
			restrict: 'AE',
			transclude: true,
			replace: true,
			template: '<div data-ng-transclude=""></div>'
		}
	})

	.directive('widgetBodyToolbar', function() {
		return {
			restrict: 'AE',
			transclude: true,
			replace: true,
			scope: {
				class: '@'
			},
			template: '<div class="widget-body-toolbar" data-ng-transclude=""></div>'
		}
	})

	.directive('widgetContent', function() {
		return {
			restrict: 'AE',
			transclude: true,
			replace: true,
			template: '<div class="widget-body" data-ng-transclude=""></div>'
		}
	})

	.directive('jqdatepicker', function () {
		return {
			restrict: 'A',
			require: 'ngModel',
			scope: { onSelect: '&jqdatepicker' },
			link: function (scope, element, attrs, ngModelCtrl) {
				element.datepicker({
					changeMonth: true,
					changeYear: true,
					numberOfMonths: 1,
					dateFormat: 'yy-mm-dd',
					prevText: attrs.pretext,
					nextText: '',
					onSelect: function (date) {
						scope.onSelect()(date);
						scope.$apply();
					}
				});
			}
		}
	})
;