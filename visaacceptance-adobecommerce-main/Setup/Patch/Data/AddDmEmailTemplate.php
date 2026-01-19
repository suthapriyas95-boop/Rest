<?php

/**
 * Copyright © 2018 CyberSource. All rights reserved.
 * See accompanying LICENSE.txt for applicable terms of use and license.
 */

declare(strict_types=1);

namespace CyberSource\Payment\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Patch is mechanism, that allows to do atomic upgrade data changes
 */
class AddDmEmailTemplate implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface $moduleDataSetup
     */
    private $moduleDataSetup;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(ModuleDataSetupInterface $moduleDataSetup)
    {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    /**
     * Do Upgrade
     *
     * @return void
     */
    public function apply()
    {

        $templateCode = 'DM Fail Transaction';

        $select = $this->moduleDataSetup->getConnection()->select()
                    ->from($this->moduleDataSetup->getTable('email_template'), ['template_id'])
                    ->where('template_code = ?', $templateCode);

        $templateId = $this->moduleDataSetup->getConnection()->fetchOne($select);

        if (!$templateId) {

             /**
             * Prepare database for install
             */
            $this->moduleDataSetup->getConnection()->startSetup();

            $email_content = $this->getEmailContent();
            $subject = '{{trans "your %store_name order has been cancelled" store_name=$store.getFrontendName()}}';

            $data[] = [
                'template_code' => $templateCode,
                'template_text' => $email_content,
                'template_styles' => '',
                'template_type' => 2,
                'template_subject' => $subject,
                'template_sender_name' => '',
                'template_sender_email' => '',
            ];

            $this->moduleDataSetup->getConnection()->insertArray(
                $this->moduleDataSetup->getTable('email_template'),
                [
                    'template_code',
                    'template_text',
                    'template_styles',
                    'template_type',
                    'template_subject',
                    'template_sender_name',
                    'template_sender_email',
                ],
                $data
            );

            /**
             * Prepare database after install
             */
            $this->moduleDataSetup->getConnection()->endSetup();
        }
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * Get email content
     *
     * @return string
     */
    private function getEmailContent()
    {

        $emailContent = '{{template config_path="design/email/header_template"}}
		<table>
			<tr class="email-intro">
				<td>
					<p class="greeting">{{trans "%name," name=$order.getCustomerName()}}</p>
					<p>
						{{trans
							"Your order #%increment_id has been cancelled by our fraud detection system.
							<strong>%order_status</strong>."
							increment_id=$order.increment_id
							order_status=$order.getStatusLabel() |raw}}
					</p>

					<p>
						{{trans "We apologize for any inconvenience and urge you to contact us by email: 
						<a href=\"mailto:%store_email\">%store_email</a>" store_email=$store_email |raw}}
						{{depend store_phone}}
						{{trans "or call us at 
						<a href=\"tel:%store_phone">%store_phone</a>\" store_phone=$store_phone |raw}}
						{{/depend}} if you believe this was cancelled in error.
						{{depend store_hours}}
						{{trans "Our hours are
						<span class=\"no-link\">%store_hours</span>." store_hours=$store_hours |raw}}
						{{/depend}}
					</p>
				</td>
			</tr>
			<tr class="email-information">
				<td>
					{{depend comment}}
					<table class="message-info">
						<tr>
							<td>
								{{var comment|escape|nl2br}}
							</td>
						</tr>
					</table>
					{{/depend}}
				</td>
			</tr>
		</table>
		{{template config_path="design/email/footer_template"}}';

        return $emailContent;
    }
}
