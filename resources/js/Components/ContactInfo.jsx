import React from 'react';
import { PhoneIcon, EnvelopeIcon, GlobeAltIcon } from '@heroicons/react/24/outline';

export default function ContactInfo({ chatbot, className = '' }) {
    const contactSettings = chatbot?.contact_settings;

    // Don't render if contact settings are not enabled or configured
    if (!contactSettings || !contactSettings.enabled) {
        return null;
    }

    const hasContactMethods = contactSettings.whatsapp_number ||
                             contactSettings.phone_number ||
                             contactSettings.email_address ||
                             contactSettings.support_email ||
                             contactSettings.support_url;

    if (!hasContactMethods) {
        return null;
    }

    return (
        <div className={`bg-gray-50 rounded-lg p-4 border border-gray-200 ${className}`}>
            <div className="flex items-center mb-3">
                <PhoneIcon className="h-5 w-5 text-gray-600 mr-2" />
                <h3 className="text-sm font-medium text-gray-900">Contact Support</h3>
            </div>

            {contactSettings.support_message && (
                <p className="text-sm text-gray-700 mb-3">
                    {contactSettings.support_message}
                </p>
            )}

            <div className="space-y-2">
                {contactSettings.whatsapp_number && (
                    <div className="flex items-center">
                        <span className="text-sm font-medium text-gray-700 w-20">WhatsApp:</span>
                        <a
                            href={`https://wa.me/${contactSettings.whatsapp_number.replace(/[^0-9]/g, '')}`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-green-600 hover:text-green-800 underline text-sm"
                        >
                            {contactSettings.whatsapp_number}
                        </a>
                    </div>
                )}

                {/* Commented out phone number section
                {contactSettings.phone_number && (
                    <div className="flex items-center">
                        <span className="text-sm font-medium text-gray-700 w-20">Phone:</span>
                        <a
                            href={`tel:${contactSettings.phone_number.replace(/\s/g, '')}`}
                            className="text-blue-600 hover:text-blue-800 underline text-sm"
                        >
                            {contactSettings.phone_number}
                        </a>
                    </div>
                )}
                */}

                {contactSettings.email_address && (
                    <div className="flex items-center">
                        <span className="text-sm font-medium text-gray-700 w-20">Email:</span>
                        <a
                            href={`mailto:${contactSettings.email_address}`}
                            className="text-blue-600 hover:text-blue-800 underline text-sm"
                        >
                            {contactSettings.email_address}
                        </a>
                    </div>
                )}

                {contactSettings.support_email && (
                    <div className="flex items-center">
                        <span className="text-sm font-medium text-gray-700 w-20">Support:</span>
                        <a
                            href={`mailto:${contactSettings.support_email}`}
                            className="text-blue-600 hover:text-blue-800 underline text-sm"
                        >
                            {contactSettings.support_email}
                        </a>
                    </div>
                )}

                {contactSettings.support_url && (
                    <div className="flex items-center">
                        <span className="text-sm font-medium text-gray-700 w-20">Portal:</span>
                        <a
                            href={contactSettings.support_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-blue-600 hover:text-blue-800 underline text-sm"
                        >
                            {contactSettings.support_url}
                        </a>
                    </div>
                )}
            </div>

            {contactSettings.business_hours && (
                <div className="mt-3 pt-3 border-t border-gray-200">
                    <p className="text-xs text-gray-600">
                        <span className="font-medium">Business Hours:</span> {contactSettings.business_hours}
                    </p>
                </div>
            )}
        </div>
    );
}