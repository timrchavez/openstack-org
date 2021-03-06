<?php
/**
 * Copyright 2015 OpenStack Foundation
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * http://www.apache.org/licenses/LICENSE-2.0
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 **/

/**
 * Interface IPresentationSpeaker
 */
interface IPresentationSpeaker extends IEntity
{

    const AnnouncementEmailAccepted = 'ACCEPTED';
    const AnnouncementEmailRejected = 'REJECTED';
    const AnnouncementEmailAlternate = 'ALTERNATE';
    const AnnouncementEmailAcceptedAlternate = 'ACCEPTED_ALTERNATE';
    const AnnouncementEmailAcceptedRejected = 'ACCEPTED_REJECTED';
    const AnnouncementEmailAlternateRejected = 'ALTERNATE_REJECTED';

    /**
     * @return bool
     */
    public function isPendingOfRegistration();

    /**
     * @return string
     */
    public function getEmail();

    /**
     * @param ICommunityMember $member
     * @return void
     */
    public function associateMember(ICommunityMember $member);

    /**
     * @param int $summit_id
     * @return bool
     */
    public function announcementEmailAlreadySent($summit_id);

    /**
     * @param int $summit_id
     * @return string|null
     */
    public function getAnnouncementEmailTypeSent($summit_id);

    /**
     * @param string $email_type
     * @param int $summit_id
     * @return $this
     */
    public function registerAnnouncementEmailTypeSent($email_type, $summit_id);

    /**
     * @param int $summit_id
     * @return bool
     */
    public function hasRejectedPresentations($summit_id = null);

    /**
     * @param int $summit_id
     * @return bool
     */
    public function hasApprovedPresentations($summit_id = null);

    /**
     * @param int $summit_id
     * @return bool
     */
    public function hasAlternatePresentations($summit_id = null);

    /**
     * @param ISpeakerSummitRegistrationPromoCode $promo_code
     * @return $this
     */
    public function registerSummitPromoCode(ISpeakerSummitRegistrationPromoCode $promo_code);

    /**
     * @param int $summit_id
     * @return bool
     */
    public function hasSummitPromoCode($summit_id);

    /**
     * @param int $summit_id
     * @return ISpeakerSummitRegistrationPromoCode
     */
    public function getSummitPromoCode($summit_id);

    /**
     * @param int $summit_id
     * @return string
     * @throws Exception
     * @throws ValidationException
     */
    public function getSpeakerConfirmationLink($summit_id);

    /**
     * @param int $summit_id
     * @return string
     */
    public function getOnSitePhoneFor($summit_id);
}