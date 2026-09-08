<?php

namespace DanielPetrica\LaravelActivityPub\Contracts;

interface FederatableContentContract
{
    public function shouldFederate(): bool;

    public function isActivityPubPinned(): bool;

    public function activityPubActor(): ActorContract;

    public function getActivityPubId(): string;

    public function getActivityPubType(): string;

    public function getActivityPubName(): ?string;

    public function getActivityPubContent(): string;

    public function getActivityPubSummary(): ?string;

    public function getActivityPubUrl(): string;

    public function getActivityPubPublishedAt(): string;

    public function getActivityPubAttributedTo(): string;

    public function getActivityPubTo(): string;

    public function getActivityPubCc(): string;

    /** @return array<int, array{type: string, mediaType: string, url: string, name?: string}> */
    public function getActivityPubAttachments(): array;

    /** @return array<int, array{type: string, href: string, name: string}> */
    public function getActivityPubTags(): array;
}
