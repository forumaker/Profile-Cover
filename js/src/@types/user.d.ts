import 'flarum/common/models/User';

// Attributes registered on User by this extension (see forum/extend.ts and the cover handling in the API).
declare module 'flarum/common/models/User' {
  export default interface User {
    cover: () => string | null;
    cover_thumbnail: () => string | null;
    cover_position: () => number | null | undefined;
    canSetProfileCover: () => boolean;
  }
}
