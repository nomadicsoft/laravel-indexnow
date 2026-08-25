<?php

namespace NomadicSoft\LaravelIndexNow\Enums;

enum IndexNowChange: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Restored = 'restored';
}
