define(['jquery'], function ($) {
	'use strict';
	return function (config) {
		console.log('Admin order JS loaded.');
		if (window.Cybersource && typeof window.Cybersource === 'function') {
			// initialize legacy class-based Cybersource for admin flows
			window.cybersourceAdminInstance = new window.Cybersource(
				config.methodCode || 'cybersource',
				config.controller || 'admin',
				window.cybersourceAdminConfig ? window.cybersourceAdminConfig.orderSaveUrl : config.orderSaveUrl,
				config.nativeAction || ''
			);
		}
	};
});
