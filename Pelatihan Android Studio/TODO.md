# TODO: Fix Server Crashes Due to Excessive API Calls

## Pending Tasks

- [x] Add deduplication and sequential execution to `getTodayKehadiranStatus` in NetworkRepository.kt
- [x] Add deduplication and sequential execution to `getKehadiranHistory` in NetworkRepository.kt
- [x] Add debouncing (500ms) to `loadTodayKehadiranStatus` in SiswaViewModel.kt
- [x] Add debouncing (500ms) to `loadRiwayat` in SiswaViewModel.kt
- [x] Test the fixes to ensure server stability
