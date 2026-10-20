<?php

declare(strict_types=1);

namespace ZipWarden;

/**
 * Stable error codes. See docs/SPEC.md, section 5.
 */
enum ErrorCode: string
{
    case InvalidArchive = 'INVALID_ARCHIVE';
    case UnsupportedFeature = 'UNSUPPORTED_FEATURE';
    case PathTraversal = 'PATH_TRAVERSAL';
    case AbsolutePath = 'ABSOLUTE_PATH';
    case NullByte = 'NULL_BYTE';
    case InvalidPath = 'INVALID_PATH';
    case DuplicateEntry = 'DUPLICATE_ENTRY';
    case Symlink = 'SYMLINK';
    case SpecialFile = 'SPECIAL_FILE';
    case ArchiveTooLarge = 'ARCHIVE_TOO_LARGE';
    case TooManyEntries = 'TOO_MANY_ENTRIES';
    case EntrySizeExceeded = 'ENTRY_SIZE_EXCEEDED';
    case TotalSizeExceeded = 'TOTAL_SIZE_EXCEEDED';
    case RatioExceeded = 'RATIO_EXCEEDED';
    case DepthExceeded = 'DEPTH_EXCEEDED';
    case NestedArchive = 'NESTED_ARCHIVE';
    case SizeMismatch = 'SIZE_MISMATCH';
    case Timeout = 'TIMEOUT';
    case ExtensionNotAllowed = 'EXTENSION_NOT_ALLOWED';
    case DestinationNotEmpty = 'DESTINATION_NOT_EMPTY';
    case IoError = 'IO_ERROR';
}
