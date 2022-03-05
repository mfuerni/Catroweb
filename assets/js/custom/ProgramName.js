import $ from 'jquery'

export function ProgramName (programId, usersLanguage, myProgram, customTranslationApi, editor) {
  const name = $('#name')
  const editProgramButton = $('#edit-program-button')

  if (!myProgram) {
    customTranslationApi.getCustomTranslation(
      programId,
      usersLanguage.substring(0, 2),
      setName
    )

    function setName (value) {
      name.text(value)
    }
  }

  editProgramButton.on('click', () => {
    editor.show()
  })
}