@echo off
echo Creating PurchaseReturns directory and copying files...
mkdir "D:\Kiot\kiotviet-sapo\resources\js\Pages\PurchaseReturns" 2>nul
copy "D:\Kiot\kiotviet-clone.worktrees\copilot-worktree-2026-04-07T09-54-25\resources\js\Pages\PurchaseReturns\Index.vue" "D:\Kiot\kiotviet-sapo\resources\js\Pages\PurchaseReturns\Index.vue"
copy "D:\Kiot\kiotviet-clone.worktrees\copilot-worktree-2026-04-07T09-54-25\resources\js\Pages\PurchaseReturns\Create.vue" "D:\Kiot\kiotviet-sapo\resources\js\Pages\PurchaseReturns\Create.vue"
copy "D:\Kiot\kiotviet-clone.worktrees\copilot-worktree-2026-04-07T09-54-25\resources\js\Pages\PurchaseReturns\Show.vue" "D:\Kiot\kiotviet-sapo\resources\js\Pages\PurchaseReturns\Show.vue"
echo Done!
pause
